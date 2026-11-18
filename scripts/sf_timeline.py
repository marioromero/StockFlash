#!/usr/bin/env python3
"""StockFlash local stage timeline (stdlib only, deterministic, local-only).

Events are appended to docs/work/.run/<ID>.timeline.jsonl (git-ignored) and a
single optional `telemetry` subtree is maintained in the existing local
checkpoint docs/work/.run/<ID>.json. No network calls, no third-party packages,
no free-text payloads: only closed enumerations and allowlisted gate ids.

CLI:
    py -3 scripts/sf_timeline.py begin FEATURE STAGE --actor ROLE [--attempt N] [--source SRC]
    py -3 scripts/sf_timeline.py end   FEATURE STAGE --actor ROLE --status PASS|FAIL|PARTIAL|BLOCK [--span SPAN] [--attempt N] [--source SRC]
    py -3 scripts/sf_timeline.py status FEATURE
    py -3 scripts/sf_timeline.py summary FEATURE

Test-only: requires SF_TIMELINE_DIR (absolute path) plus SF_TIMELINE_TEST=1 to
redirect the timeline/checkpoint; otherwise docs/work/.run is used.
"""
from __future__ import annotations

import datetime as _dt
import json
import os
import re
import sys
import time
import uuid
from pathlib import Path
from typing import Any, Callable

ROOT = Path(__file__).resolve().parents[1]
RUN_DIR = ROOT / "docs" / "work" / ".run"

# Strict identifiers: no paths, no spaces, no lowercase, no traversal.
FEATURE_RE = re.compile(r"^[A-Z0-9][A-Z0-9-]{2,63}$")
GATE_RE = re.compile(r"^[A-Za-z0-9_.:-]{1,64}$")
SPAN_RE = re.compile(r"^[0-9a-f]{8}$")

STAGES = ("preflight", "implementation", "verification", "qa", "correction", "publication", "ci")
ROLES = ("orchestrator", "implementer", "reviewer", "publisher", "verifier", "system")
STATUSES = ("PASS", "FAIL", "PARTIAL", "BLOCK")
SOURCES = ("observed_stage_boundary", "native_gate_runner")

EVENT_VERSION = 1
LOCK_TIMEOUT_S = 2.0
LOCK_SLEEP_S = 0.02
LOCK_STALE_S = 60.0

EVENT_KEYS = (
    "v", "feature", "type", "stage", "actor", "span", "ts", "epoch_ms",
    "mono_ms", "attempt", "outcome", "source", "gate_id", "duration_ms",
    "wall_skew_ms",
)


class SpanNotFound(Exception):
    """No unique open span matches the requested close."""


class SpanAmbiguous(Exception):
    """More than one open span matches the requested close."""


def _warn(code: str) -> None:
    print(code, file=sys.stderr)


def _now_ms() -> int:
    return int(time.time() * 1000)


def _mono_ms() -> int:
    return time.monotonic_ns() // 1_000_000


def _iso_utc(epoch_ms: int) -> str:
    seconds, millis = divmod(int(epoch_ms), 1000)
    stamp = _dt.datetime.fromtimestamp(seconds, tz=_dt.timezone.utc)
    return stamp.strftime("%Y-%m-%dT%H:%M:%S.") + f"{millis:03d}Z"


def _validate_feature(feature: Any) -> str:
    if not isinstance(feature, str) or FEATURE_RE.fullmatch(feature) is None:
        raise ValueError("INVALID_FEATURE_ID")
    return feature


def _validate_choice(value: Any, allowed: tuple[str, ...], code: str) -> str:
    if value not in allowed:
        raise ValueError(code)
    return value


def _validate_attempt(attempt: Any) -> int:
    if not isinstance(attempt, int) or isinstance(attempt, bool) or attempt < 1:
        raise ValueError("INVALID_ATTEMPT")
    return attempt


def _run_dir(base_dir: Path | str | None = None) -> Path:
    if base_dir is not None:
        return Path(base_dir)
    override = os.getenv("SF_TIMELINE_DIR", "")
    if override and os.getenv("SF_TIMELINE_TEST") == "1":
        candidate = Path(override)
        if candidate.is_absolute():
            return candidate
    return RUN_DIR


def _timeline_path(feature: str, base_dir: Path | str | None = None) -> Path:
    return _run_dir(base_dir) / f"{feature}.timeline.jsonl"


def _lock_path(feature: str, base_dir: Path | str | None = None) -> Path:
    return _run_dir(base_dir) / f"{feature}.timeline.lock"


def _checkpoint_path(feature: str, base_dir: Path | str | None = None) -> Path:
    return _run_dir(base_dir) / f"{feature}.json"


class _Lock:
    """Bounded cross-platform lock file; append anyway after timeout."""

    def __init__(self, path: Path) -> None:
        self.path = path
        self.fd: int | None = None
        self.acquired = False

    def __enter__(self) -> "_Lock":
        deadline = time.monotonic() + LOCK_TIMEOUT_S
        stale_retry = True
        while True:
            try:
                self.fd = os.open(str(self.path), os.O_CREAT | os.O_EXCL | os.O_WRONLY)
                self.acquired = True
                return self
            except FileExistsError:
                if stale_retry:
                    try:
                        age = time.time() - self.path.stat().st_mtime
                    except OSError:
                        age = 0.0
                    if age > LOCK_STALE_S:
                        stale_retry = False
                        try:
                            os.unlink(str(self.path))
                        except OSError:
                            pass
                        continue
                if time.monotonic() >= deadline:
                    _warn("TELEMETRY_LOCK_TIMEOUT")
                    return self
                time.sleep(LOCK_SLEEP_S)
            except OSError:
                _warn("TELEMETRY_LOCK_UNAVAILABLE")
                return self

    def __exit__(self, exc_type, exc, tb) -> None:
        if not self.acquired:
            return
        if self.fd is not None:
            try:
                os.close(self.fd)
            except OSError:
                pass
            self.fd = None
        try:
            os.unlink(str(self.path))
        except OSError:
            pass


def _last_byte_is_newline(path: Path) -> bool:
    try:
        if not path.exists() or path.stat().st_size == 0:
            return True
        with open(path, "rb") as handle:
            handle.seek(-1, os.SEEK_END)
            return handle.read(1) == b"\n"
    except OSError:
        return True


def _append_event(feature: str, event: dict, base_dir: Path | str | None = None) -> bool:
    path = _timeline_path(feature, base_dir)
    line = json.dumps(event, ensure_ascii=True, separators=(",", ":")) + "\n"
    payload = line.encode("utf-8")
    try:
        path.parent.mkdir(parents=True, exist_ok=True)
        with _Lock(_lock_path(feature, base_dir)):
            if not _last_byte_is_newline(path):
                payload = b"\n" + payload
            fd = os.open(str(path), os.O_APPEND | os.O_CREAT | os.O_WRONLY, 0o644)
            try:
                os.write(fd, payload)
            finally:
                os.close(fd)
        return True
    except OSError:
        _warn("TELEMETRY_UNAVAILABLE")
        return False


def _update_checkpoint(feature: str, event: dict, base_dir: Path | str | None = None) -> bool:
    path = _checkpoint_path(feature, base_dir)
    try:
        before = path.stat()
    except FileNotFoundError:
        _warn("TELEMETRY_CHECKPOINT_SKIPPED")
        return False
    except OSError:
        _warn("TELEMETRY_CHECKPOINT_SKIPPED")
        return False
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        _warn("TELEMETRY_CHECKPOINT_SKIPPED")
        return False
    if not isinstance(data, dict):
        _warn("TELEMETRY_CHECKPOINT_SKIPPED")
        return False
    data["telemetry"] = {
        "current_stage": event["stage"],
        "last_event": {
            "type": event["type"],
            "stage": event["stage"],
            "actor": event["actor"],
            "outcome": event["outcome"],
            "ts": event["ts"],
        },
        "updated_at": event["ts"],
    }
    tmp = path.with_name(f"{path.name}.{os.getpid()}.{uuid.uuid4().hex[:6]}.tmp")
    try:
        tmp.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        try:
            after = path.stat()
        except OSError:
            after = None
        if after is None or (after.st_mtime_ns, after.st_size) != (before.st_mtime_ns, before.st_size):
            try:
                tmp.unlink()
            except OSError:
                pass
            _warn("TELEMETRY_CHECKPOINT_CONFLICT")
            return False
        os.replace(str(tmp), str(path))
        return True
    except OSError:
        _warn("TELEMETRY_UNAVAILABLE")
        try:
            tmp.unlink()
        except OSError:
            pass
        return False


def _event(
    *,
    feature: str,
    etype: str,
    stage: str,
    actor: str,
    span: str,
    epoch_ms: int,
    mono_ms: int | None,
    attempt: int | None,
    outcome: str | None,
    source: str,
    gate_id: str | None = None,
    duration_ms: int | None = None,
    wall_skew_ms: int | None = None,
) -> dict:
    return {
        "v": EVENT_VERSION,
        "feature": feature,
        "type": etype,
        "stage": stage,
        "actor": actor,
        "span": span,
        "ts": _iso_utc(epoch_ms),
        "epoch_ms": int(epoch_ms),
        "mono_ms": mono_ms,
        "attempt": attempt,
        "outcome": outcome,
        "source": source,
        "gate_id": gate_id,
        "duration_ms": duration_ms,
        "wall_skew_ms": wall_skew_ms,
    }


def _is_opt_int(value: Any) -> bool:
    return value is None or (isinstance(value, int) and not isinstance(value, bool))


def _valid_event(obj: dict, feature: str) -> bool:
    if obj.get("v") != EVENT_VERSION or obj.get("feature") != feature:
        return False
    if obj.get("type") not in ("begin", "end", "gate"):
        return False
    if obj.get("stage") not in STAGES or obj.get("actor") not in ROLES:
        return False
    if obj.get("source") not in SOURCES:
        return False
    span = obj.get("span")
    if not isinstance(span, str) or SPAN_RE.fullmatch(span) is None:
        return False
    if not isinstance(obj.get("epoch_ms"), int):
        return False
    if not _is_opt_int(obj.get("mono_ms")):
        return False
    attempt = obj.get("attempt")
    if attempt is not None and (
        not isinstance(attempt, int) or isinstance(attempt, bool) or attempt < 1
    ):
        return False
    if not _is_opt_int(obj.get("duration_ms")):
        return False
    if not _is_opt_int(obj.get("wall_skew_ms")):
        return False
    if obj.get("outcome") is not None and obj.get("outcome") not in STATUSES:
        return False
    gate_id = obj.get("gate_id")
    if gate_id is not None and (not isinstance(gate_id, str) or GATE_RE.fullmatch(gate_id) is None):
        return False
    if obj.get("type") == "gate" and (gate_id is None or obj.get("duration_ms") is None):
        return False
    return True


def _read_raw(feature: str, base_dir: Path | str | None = None) -> tuple[list[dict], int, bool]:
    path = _timeline_path(feature, base_dir)
    if not path.exists():
        return [], 0, False
    try:
        raw = path.read_text(encoding="utf-8")
    except OSError:
        return [], 0, True
    events: list[dict] = []
    invalid = 0
    for line in raw.splitlines():
        stripped = line.strip()
        if not stripped:
            continue
        try:
            obj = json.loads(stripped)
        except (ValueError, json.JSONDecodeError):
            invalid += 1
            continue
        if not isinstance(obj, dict) or not _valid_event(obj, feature):
            invalid += 1
            continue
        events.append(obj)
    return events, invalid, True


def _split_spans(events: list[dict]) -> tuple[list[dict], list[tuple[dict, dict]]]:
    begins: dict[str, dict] = {}
    ends: dict[str, dict] = {}
    for event in events:
        if event["type"] == "begin":
            begins.setdefault(event["span"], event)
        elif event["type"] == "end":
            ends[event["span"]] = event
    open_spans: list[dict] = []
    closed: list[tuple[dict, dict]] = []
    for span, begin in begins.items():
        if span in ends:
            closed.append((begin, ends[span]))
        else:
            open_spans.append(begin)
    return open_spans, closed


def _union_ms(intervals: list[tuple[int, int]]) -> int:
    if not intervals:
        return 0
    ordered = sorted(intervals)
    total = 0
    cur_start, cur_end = ordered[0]
    for start, end in ordered[1:]:
        if start <= cur_end:
            cur_end = max(cur_end, end)
        else:
            total += cur_end - cur_start
            cur_start, cur_end = start, end
    total += cur_end - cur_start
    return total


def begin_span(
    feature: str,
    stage: str,
    actor: str,
    *,
    attempt: int | None = None,
    source: str = "observed_stage_boundary",
    base_dir: Path | str | None = None,
    now: Callable[[], int] | None = None,
) -> dict | None:
    feature = _validate_feature(feature)
    _validate_choice(stage, STAGES, "INVALID_STAGE")
    _validate_choice(actor, ROLES, "INVALID_ROLE")
    _validate_choice(source, SOURCES, "INVALID_SOURCE")
    if attempt is not None:
        _validate_attempt(attempt)
    epoch_ms = int((now or _now_ms)())
    event = _event(
        feature=feature,
        etype="begin",
        stage=stage,
        actor=actor,
        span=uuid.uuid4().hex[:8],
        epoch_ms=epoch_ms,
        mono_ms=_mono_ms(),
        attempt=attempt,
        outcome=None,
        source=source,
    )
    if not _append_event(feature, event, base_dir):
        return None
    _update_checkpoint(feature, event, base_dir)
    return event


def end_span(
    feature: str,
    stage: str,
    actor: str,
    *,
    status: str,
    span: str | None = None,
    attempt: int | None = None,
    source: str = "observed_stage_boundary",
    base_dir: Path | str | None = None,
    now: Callable[[], int] | None = None,
) -> dict | None:
    feature = _validate_feature(feature)
    _validate_choice(stage, STAGES, "INVALID_STAGE")
    _validate_choice(actor, ROLES, "INVALID_ROLE")
    _validate_choice(status, STATUSES, "INVALID_STATUS")
    _validate_choice(source, SOURCES, "INVALID_SOURCE")
    if attempt is not None:
        _validate_attempt(attempt)
    if span is not None and SPAN_RE.fullmatch(span) is None:
        raise ValueError("INVALID_SPAN")
    events, _invalid, _exists = _read_raw(feature, base_dir)
    open_spans, _closed = _split_spans(events)
    candidates = [
        begin for begin in open_spans
        if begin["stage"] == stage
        and begin["actor"] == actor
        and (span is None or begin["span"] == span)
    ]
    if not candidates:
        raise SpanNotFound()
    if len(candidates) > 1:
        raise SpanAmbiguous()
    begin = candidates[0]
    epoch_ms = int((now or _now_ms)())
    mono_ms = _mono_ms()
    duration_ms = epoch_ms - int(begin["epoch_ms"])
    wall_skew_ms = None
    if begin.get("mono_ms") is not None:
        wall_skew_ms = duration_ms - (mono_ms - int(begin["mono_ms"]))
    event = _event(
        feature=feature,
        etype="end",
        stage=stage,
        actor=actor,
        span=begin["span"],
        epoch_ms=epoch_ms,
        mono_ms=mono_ms,
        attempt=attempt if attempt is not None else begin.get("attempt"),
        outcome=status,
        source=source,
        duration_ms=duration_ms,
        wall_skew_ms=wall_skew_ms,
    )
    if not _append_event(feature, event, base_dir):
        return None
    _update_checkpoint(feature, event, base_dir)
    return event


def record_gate(
    feature: str,
    gate_id: str,
    *,
    outcome: str,
    duration_ms: int,
    source: str = "native_gate_runner",
    base_dir: Path | str | None = None,
    now: Callable[[], int] | None = None,
) -> None:
    try:
        feature = _validate_feature(feature)
    except ValueError:
        return None
    if not isinstance(gate_id, str) or GATE_RE.fullmatch(gate_id) is None:
        return None
    if outcome not in STATUSES or source not in SOURCES:
        return None
    if not isinstance(duration_ms, int) or isinstance(duration_ms, bool):
        return None
    event = _event(
        feature=feature,
        etype="gate",
        stage="verification",
        actor="verifier",
        span=uuid.uuid4().hex[:8],
        epoch_ms=int((now or _now_ms)()),
        mono_ms=_mono_ms(),
        attempt=None,
        outcome=outcome,
        source=source,
        gate_id=gate_id,
        duration_ms=duration_ms,
        wall_skew_ms=None,
    )
    _append_event(feature, event, base_dir)
    _update_checkpoint(feature, event, base_dir)
    return None


def read_events(feature: str, base_dir: Path | str | None = None) -> list[dict]:
    feature = _validate_feature(feature)
    events, _invalid, _exists = _read_raw(feature, base_dir)
    return events


def build_status(feature: str, base_dir: Path | str | None = None) -> dict:
    feature = _validate_feature(feature)
    events, invalid, exists = _read_raw(feature, base_dir)
    open_spans, closed = _split_spans(events)
    now = _now_ms()
    active = [
        {
            "stage": begin["stage"],
            "actor": begin["actor"],
            "span": begin["span"],
            "started_at": begin["ts"],
            "elapsed_ms": now - int(begin["epoch_ms"]),
        }
        for begin in open_spans
    ]
    closed_sorted = sorted(
        closed,
        key=lambda pair: (int(pair[1].get("epoch_ms") or 0), int(pair[0].get("epoch_ms") or 0)),
    )
    recent = [
        {
            "stage": begin["stage"],
            "actor": begin["actor"],
            "span": begin["span"],
            "started_at": begin["ts"],
            "ended_at": end["ts"],
            "outcome": end.get("outcome"),
            "duration_ms": end.get("duration_ms"),
            "wall_skew_ms": end.get("wall_skew_ms"),
        }
        for begin, end in closed_sorted
    ]
    return {
        "feature": feature,
        "exists": exists,
        "invalid_lines": invalid,
        "active": active,
        "closed": recent,
        "incomplete": bool(active),
        "no_data": not events,
    }


def build_summary(feature: str, base_dir: Path | str | None = None) -> str:
    feature = _validate_feature(feature)
    events, invalid, _exists = _read_raw(feature, base_dir)
    open_spans, closed = _split_spans(events)
    lines = [f"FICHA {feature} - TELEMETRIA LOCAL (v{EVENT_VERSION})"]
    if not events:
        lines.append("SIN_DATOS")
        lines.append(f"LINEAS_INVALIDAS: {invalid}")
        lines.append("LIMITES: espera ociosa y tiempo de inferencia del modelo = DESCONOCIDO (solo reloj de pared observado).")
        return "\n".join(lines)

    stage_counts: dict[str, int] = {}
    attempts: dict[str, set[int]] = {}
    for begin, end in closed:
        stage = begin["stage"]
        stage_counts[stage] = stage_counts.get(stage, 0) + 1
        if begin.get("attempt") is not None:
            attempts.setdefault(stage, set()).add(int(begin["attempt"]))
    stage_line = ", ".join(f"{stage}={stage_counts[stage]}" for stage in STAGES if stage in stage_counts)
    lines.append("Etapas cerradas (conteo): " + (stage_line or "ninguna"))
    attempt_line = "; ".join(
        f"{stage}=" + ",".join(str(value) for value in sorted(values))
        for stage, values in sorted(attempts.items())
    )
    lines.append("Intentos observados: " + (attempt_line or "ninguno"))

    sum_ms = sum(int(end["duration_ms"]) for _begin, end in closed if end.get("duration_ms") is not None)
    intervals: list[tuple[int, int]] = []
    for begin, end in closed:
        start = int(begin["epoch_ms"])
        stop = int(end["epoch_ms"])
        if stop > start:
            intervals.append((start, stop))
    lines.append(f"Duracion suma (ms): {sum_ms}")
    lines.append(f"Duracion union (ms): {_union_ms(intervals)}")

    now = _now_ms()
    lines.append(f"Intervalos activos: {len(open_spans)}")
    for begin in open_spans:
        lines.append(
            f"- ACTIVO {begin['stage']}/{begin['actor']} span={begin['span']} "
            f"inicio={begin['ts']} transcurrido_ms={now - int(begin['epoch_ms'])}"
        )

    native_spans = {
        begin["span"] for begin, _end in closed
        if begin["stage"] == "verification" and begin["source"] == "native_gate_runner"
    }
    lines.append(f"Ejecuciones sf_verify (native_gate_runner): {len(native_spans)}")

    gate_counts: dict[str, int] = {}
    gate_durations: dict[str, int] = {}
    for event in events:
        if event["type"] != "gate" or not event.get("gate_id"):
            continue
        gate_id = event["gate_id"]
        gate_counts[gate_id] = gate_counts.get(gate_id, 0) + 1
        gate_durations[gate_id] = gate_durations.get(gate_id, 0) + int(event.get("duration_ms") or 0)
    if gate_counts:
        gate_line = ", ".join(
            f"{gate_id}={gate_counts[gate_id]}/{gate_durations[gate_id]}ms"
            for gate_id in sorted(gate_counts)
        )
    else:
        gate_line = "ninguno"
    lines.append("Gates (conteo/duracion): " + gate_line)
    lines.append(f"LINEAS_INVALIDAS: {invalid}")
    lines.append("LIMITES: espera ociosa y tiempo de inferencia del modelo = DESCONOCIDO (solo reloj de pared observado).")
    return "\n".join(lines)


def _print_status(status: dict) -> None:
    print(f"FICHA {status['feature']} - ESTADO DE ETAPAS")
    if status["no_data"]:
        print("SIN_DATOS")
    print(f"ACTIVOS: {len(status['active'])}")
    for active in status["active"]:
        print(
            f"- ACTIVO {active['stage']}/{active['actor']} span={active['span']} "
            f"inicio={active['started_at']} transcurrido_ms={active['elapsed_ms']}"
        )
    print(f"CERRADOS: {len(status['closed'])}")
    for closed in status["closed"][-5:]:
        print(
            f"- CERRADO {closed['stage']}/{closed['actor']} span={closed['span']} "
            f"outcome={closed['outcome']} duracion_ms={closed['duration_ms']}"
        )
    if status["incomplete"]:
        print(f"INCOMPLETO: {len(status['active'])} etapa(s) sin cierre")
    if status["invalid_lines"]:
        print(f"LINEAS_INVALIDAS={status['invalid_lines']}")


def _parse_options(tokens: list[str], allow_status: bool, allow_span: bool) -> dict | None:
    opts: dict[str, Any] = {}
    index = 0
    while index < len(tokens):
        token = tokens[index]
        if token not in ("--actor", "--attempt", "--source", "--status", "--span"):
            return None
        if token == "--status" and not allow_status:
            return None
        if token == "--span" and not allow_span:
            return None
        if index + 1 >= len(tokens):
            return None
        value = tokens[index + 1]
        key = token[2:].replace("-", "_")
        if key in opts:
            return None
        if key == "attempt":
            try:
                opts[key] = int(value)
            except ValueError:
                return None
        else:
            opts[key] = value
        index += 2
    return opts


def main(argv: list[str] | None = None) -> int:
    tokens = list(sys.argv[1:] if argv is None else argv)
    if not tokens:
        print("INVALID_ARGS")
        return 2
    command = tokens[0]

    if command in ("status", "summary"):
        if len(tokens) != 2:
            print("INVALID_ARGS")
            return 2
        try:
            feature = _validate_feature(tokens[1])
        except ValueError:
            print("INVALID_FEATURE_ID")
            return 2
        if command == "status":
            _print_status(build_status(feature))
        else:
            print(build_summary(feature))
        return 0

    if command not in ("begin", "end"):
        print("INVALID_ARGS")
        return 2
    if len(tokens) < 3:
        print("INVALID_ARGS")
        return 2
    feature = tokens[1]
    stage = tokens[2]
    opts = _parse_options(tokens[3:], allow_status=(command == "end"), allow_span=(command == "end"))
    if opts is None or "actor" not in opts:
        print("INVALID_ARGS")
        return 2
    try:
        _validate_feature(feature)
        _validate_choice(stage, STAGES, "INVALID_STAGE")
        _validate_choice(opts["actor"], ROLES, "INVALID_ROLE")
        if "source" in opts:
            _validate_choice(opts["source"], SOURCES, "INVALID_SOURCE")
        if "attempt" in opts:
            _validate_attempt(opts["attempt"])
        if command == "end":
            if "status" not in opts:
                print("INVALID_STATUS")
                return 2
            _validate_choice(opts["status"], STATUSES, "INVALID_STATUS")
            if "span" in opts and SPAN_RE.fullmatch(opts["span"]) is None:
                print("INVALID_SPAN")
                return 2
    except ValueError as exc:
        print(str(exc) or "INVALID_ARGS")
        return 2

    if command == "begin":
        event = begin_span(
            feature,
            stage,
            opts["actor"],
            attempt=opts.get("attempt"),
            source=opts.get("source", "observed_stage_boundary"),
        )
        if event is not None:
            print(event["span"])
        return 0

    try:
        event = end_span(
            feature,
            stage,
            opts["actor"],
            status=opts["status"],
            span=opts.get("span"),
            attempt=opts.get("attempt"),
            source=opts.get("source", "observed_stage_boundary"),
        )
    except SpanNotFound:
        print("SPAN_NOT_FOUND")
        return 3
    except SpanAmbiguous:
        print("SPAN_AMBIGUOUS")
        return 3
    except ValueError as exc:
        print(str(exc) or "INVALID_ARGS")
        return 2
    if event is not None:
        print(event["span"])
    return 0


if __name__ == "__main__":
    sys.exit(main())
