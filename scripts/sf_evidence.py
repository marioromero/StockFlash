#!/usr/bin/env python3
"""Conservative local proof for reusing an already executed StockFlash gate run.

No test runs, model calls, session inspection or network. A missing/invalid
proof always means RUN_REQUIRED. Never use this proof for QA or GitHub CI.
"""
from __future__ import annotations

import hashlib
import json
import math
import os
import re
import subprocess
import time
import uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CACHE_VERSION = 1
MAX_AGE_SECONDS = 2 * 60 * 60
FEATURE_RE = re.compile(r"^[A-Z0-9][A-Z0-9-]{2,63}$")


def _valid_feature(feature: str) -> None:
    if not isinstance(feature, str) or not FEATURE_RE.fullmatch(feature):
        raise ValueError("INVALID_FEATURE_ID")


def _run_git(root: Path, *args: str) -> bytes:
    proc = subprocess.run(
        ["git", "-C", str(root), *args],
        stdout=subprocess.PIPE, stderr=subprocess.DEVNULL,
        check=False, timeout=15,
    )
    if proc.returncode:
        raise RuntimeError("GIT_UNAVAILABLE")
    return proc.stdout.strip() if args[:1] == ("rev-parse",) else proc.stdout


def _report_name(feature: str, name: str, kind: str = "VERIFY") -> bool:
    return bool(re.fullmatch(
        re.escape(feature) + "-" + kind + r"-[0-9]{8}-[0-9]{6}\.md",
        name,
    ))


def _report_is_pass(contents: bytes) -> bool:
    """Require exactly one explicit PASS verdict, not a stray token mention."""
    try:
        report = contents.decode("utf-8")
    except UnicodeDecodeError:
        return False
    verdicts = [
        line.partition(":")[2].strip()
        for line in report.splitlines()
        if line.startswith("Veredicto:")
    ]
    if len(verdicts) != 1:
        return False
    verdict = verdicts[0]
    return (
        verdict in ("GATES_PASS", "**GATES_PASS**")
        or verdict.startswith("**GATES_PASS** (")
        or verdict.startswith("GATES_PASS (")
    )


def _exclude_evidence(feature: str, name: str) -> bool:
    # Local proof/checkpoints are outputs, never part of the source candidate.
    # Exclude even in temporary Git fixtures without the project .gitignore.
    if name.startswith("docs/work/.run/"):
        return True
    prefix = "docs/work/"
    if not name.startswith(prefix):
        return False
    basename = name[len(prefix):]
    return _report_name(feature, basename, "VERIFY") or _report_name(feature, basename, "REVIEW")


def fingerprint(feature: str, root: Path = ROOT) -> dict:
    """Hash the complete Git-visible source tree, including untracked files.

    Deliberately conservative: unrelated tracked changes invalidate reuse.
    Only same-feature generated VERIFY/REVIEW evidence is excluded, not tests,
    packets, source, scripts, config, guide or roadmap metadata.
    """
    _valid_feature(feature)
    root = Path(root).resolve()
    head = _run_git(root, "rev-parse", "HEAD").decode("ascii")
    raw = _run_git(root, "ls-files", "--cached", "--others", "--exclude-standard", "-z")
    names = sorted({os.fsdecode(item) for item in raw.split(b"\0") if item})
    digest = hashlib.sha256()
    digest.update(b"SF-QAEFF1\0" + head.encode("ascii") + b"\0")
    count = 0
    for name in names:
        normalized = name.replace("\\", "/")
        if _exclude_evidence(feature, normalized):
            continue
        # Never inspect real env files or private keys even for hashing.
        parts = Path(normalized).parts
        if any(p == ".env" or p.startswith(".env.") and p != ".env.example"
               or p.endswith((".pem", ".key", ".p12", ".pfx")) for p in parts):
            raise RuntimeError("SENSITIVE_PATH_IN_GIT")
        path = root / normalized
        digest.update(os.fsencode(normalized) + b"\0")
        if path.is_symlink():
            digest.update(b"SYMLINK\0" + os.fsencode(os.readlink(path)) + b"\0")
        elif path.is_file():
            digest.update(b"FILE\0")
            with path.open("rb") as handle:
                while chunk := handle.read(1024 * 1024):
                    digest.update(chunk)
            digest.update(b"\0END\0")
        elif not path.exists():
            digest.update(b"MISSING\0")
        else:
            raise RuntimeError("UNSUPPORTED_GIT_PATH")
        count += 1
    return {"head": head, "digest": digest.hexdigest(), "file_count": count}


def _cache_path(feature: str, root: Path) -> Path:
    _valid_feature(feature)
    return Path(root) / "docs" / "work" / ".run" / (feature + ".gate-evidence.json")


def invalidate(feature: str, root: Path = ROOT) -> None:
    """Before a real attempt, invalidate any older PASS; failures stay invalid."""
    _cache_path(feature, root).unlink(missing_ok=True)


def record_success(feature: str, before: dict, report: Path, root: Path = ROOT) -> bool:
    """Write proof only after the runner executed every gate with GATES_PASS."""
    _valid_feature(feature)
    root = Path(root).resolve()
    if before != fingerprint(feature, root):
        return False  # Source mutated while gates were running; never reuse.
    report = Path(report).resolve()
    if report.parent != (root / "docs" / "work") or not _report_name(feature, report.name):
        return False
    contents = report.read_bytes()
    if not _report_is_pass(contents):
        return False
    proof = {
        "version": CACHE_VERSION,
        "feature": feature,
        "created_at": time.time(),
        "run_id": uuid.uuid4().hex,
        "head": before["head"],
        "fingerprint": before["digest"],
        "file_count": before["file_count"],
        "report": report.name,
        "report_sha256": hashlib.sha256(contents).hexdigest(),
    }
    path = _cache_path(feature, root)
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_name(path.name + "." + uuid.uuid4().hex + ".tmp")
    try:
        with tmp.open("x", encoding="utf-8") as stream:
            json.dump(proof, stream, separators=(",", ":"), sort_keys=True)
        os.replace(tmp, path)
    finally:
        tmp.unlink(missing_ok=True)
    return True


def check(feature: str, root: Path = ROOT, now: float | None = None) -> tuple[bool, str, str | None]:
    """Return (reusable, reason, original report). All uncertainty fails closed."""
    _valid_feature(feature)
    root = Path(root).resolve()
    path = _cache_path(feature, root)
    try:
        if path.is_symlink():
            return False, "PROOF_FILE_SYMLINK", None
        proof = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return False, "NO_VALID_PROOF", None
    if not isinstance(proof, dict) or proof.get("version") != CACHE_VERSION or proof.get("feature") != feature:
        return False, "PROOF_SCHEMA_MISMATCH", None
    created = proof.get("created_at")
    reference_now = time.time() if now is None else now
    # Reject NaN/Infinity/huge integers before age comparisons, as NaN
    # otherwise makes BOTH comparisons false and silently bypasses expiry.
    for value in (created, reference_now):
        if (type(value) not in (int, float) or
                not (0 < value < 2 ** 53) or not math.isfinite(value)):
            return False, "PROOF_TIME_INVALID", None
    age = reference_now - created
    if not math.isfinite(age):
        return False, "PROOF_TIME_INVALID", None
    if age < -30 or age > MAX_AGE_SECONDS:
        return False, "PROOF_EXPIRED", None
    name = proof.get("report")
    if not isinstance(name, str) or not _report_name(feature, name):
        return False, "PROOF_REPORT_INVALID", None
    report = root / "docs" / "work" / name
    try:
        # Do not follow a substituted evidence symlink.
        if report.is_symlink():
            return False, "PROOF_REPORT_INVALID", None
        contents = report.read_bytes()
    except OSError:
        return False, "PROOF_REPORT_MISSING", None
    if not _report_is_pass(contents):
        return False, "PROOF_REPORT_NOT_PASS", None
    actual = hashlib.sha256(contents).hexdigest()
    if actual != proof.get("report_sha256"):
        return False, "PROOF_REPORT_CHANGED", None
    try:
        state = fingerprint(feature, root)
    except (RuntimeError, OSError, subprocess.SubprocessError, UnicodeError):
        return False, "SOURCE_FINGERPRINT_UNAVAILABLE", None
    if (state["head"] != proof.get("head") or
            state["digest"] != proof.get("fingerprint") or
            state["file_count"] != proof.get("file_count")):
        return False, "SOURCE_CHANGED", None
    return True, "REUSE_VALID", name
