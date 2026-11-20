#!/usr/bin/env python3
"""HQAEFF-002: final-gate coverage policy for future StockFlash packets.

This is a deterministic *plan validator*, not a gate skipper. Historic packet
definitions remain unchanged. New definitions must include the same full
coverage with no redundant focused PHP or standalone duplicated TS pass.
No subprocesses, remote IO, tokens, secrets or test execution.
"""
from __future__ import annotations

import json
import xml.etree.ElementTree as ET
from pathlib import Path

# One-time baseline taken at HQAEFF-002 merge: do NOT add newly authored
# production packets here. Older APPROVED gate lists must keep their contract.
HISTORICAL_PACKETS = frozenset({
    "BOOT-001", "HARNESS-001", "BRAND-001", "CI-001", "STAGING-001",
    "AUTH-001", "USERS-001", "BRANCH-001", "DOCS-001", "ROADMAP-001",
    "HCTX-001", "CFG-001", "CFG-002", "CFG-003", "CORESEQ-001", "CORELIC-001", "COREMOD-001",
    "COREPIN-001", "CATDB-001", "CATMAST-001", "CATPROD-001",
    "INVCORE-001", "INVWRITE-001", "INVKARDEX-001", "INVREORDER-001",
    "INVCSV-001", "INVTRANSFER-001", "SETUP-001", "INSTALL-001",
    "HTIME-001",
})
# Reserved names in the existing synthetic sf_verify integration tests.
SYNTHETIC_TEST_IDS = frozenset({"TEST-001", "DEMO-001"})
FULL_PHP = ("php", "artisan", "test")
NPM_BUILD = ("npm", "run", "build")
NPM_TYPECHECK = ("npm", "run", "typecheck")
TYPED_VITE_BUILD = "vue-tsc --noEmit && vite build"
VITE_BUILD = "vite build"
REAL_TYPECHECK = "vue-tsc --noEmit"


def _required_command(gate: dict, cmd: tuple[str, ...]) -> bool:
    return gate.get("kind") == "shell" and gate.get("mandatory") is True and tuple(gate.get("cmd", ())) == cmd


def _phpunit_covers_all_feature_tests(root: Path) -> bool:
    try:
        tree = ET.parse(root / "phpunit.xml")
    except (OSError, ET.ParseError):
        return False
    # No test suite exclusion is permitted: it may silently hide Inventory,
    # security or other tests from an otherwise complete-looking suite.
    if any(node.tag.rsplit("}", 1)[-1] == "exclude" for node in tree.iter()):
        return False
    roots = [
        node for node in tree.findall(".//testsuite/directory")
        if (node.text or "").strip().replace(chr(92), "/").rstrip("/")
        in {"tests/Feature", "tests/Unit"}
    ]
    # A fake suffix can make an apparent test root discover no PHP tests.
    if any(node.attrib and node.attrib != {"suffix": ".php"} for node in roots):
        return False
    paths = {
        (node.text or "").strip().replace(chr(92), "/").rstrip("/")
        for node in roots
    }
    return {"tests/Feature", "tests/Unit"}.issubset(paths)

def _npm_script_coverage(root: Path) -> tuple[bool, bool, bool]:
    """Return (real Vite build, combined TS check, real standalone TS check).

    Recognize only exact reviewed scripts. Never infer coverage from names.
    """
    try:
        data = json.loads((root / "package.json").read_text(encoding="utf-8"))
    except (OSError, ValueError, TypeError):
        return False, False, False
    if not isinstance(data, dict) or not isinstance(data.get("scripts"), dict):
        return False, False, False
    scripts = data["scripts"]
    build = scripts.get("build")
    typed_build = build == TYPED_VITE_BUILD
    valid_build = typed_build or build == VITE_BUILD
    valid_standalone = scripts.get("typecheck") == REAL_TYPECHECK
    return valid_build, typed_build, valid_standalone


def _npm_build_includes_typecheck(root: Path) -> bool:
    return _npm_script_coverage(root)[1]

def check_final_plan(packet: str, gates: list[dict], root: Path) -> list[str]:
    """Return policy violations; empty means safe. Existing packets unchanged.

    This never removes a test or changes the GATES_BY_PACKET list.
    New plans intentionally fail BEFORE running expensive gates if malformed.
    """
    if packet in HISTORICAL_PACKETS or packet in SYNTHETIC_TEST_IDS:
        return []
    problems: list[str] = []
    if not isinstance(gates, list) or not gates or any(not isinstance(g, dict) for g in gates):
        return ["INVALID_GATE_LIST"]

    full_php = 0
    focused_php: list[str] = []
    builds = 0
    typechecks = 0
    for gate in gates:
        if gate.get("kind") != "shell":
            continue
        cmd = gate.get("cmd")
        if not isinstance(cmd, list) or not all(isinstance(v, str) for v in cmd):
            problems.append("INVALID_SHELL_COMMAND")
            continue
        tokens = tuple(cmd)
        if tokens[:3] == FULL_PHP:
            if _required_command(gate, FULL_PHP):
                full_php += 1
            else:
                focused_php.append(gate.get("id", "UNKNOWN"))
        elif (tokens and tokens[0] in ("vendor/bin/phpunit", "./vendor/bin/phpunit")
              or tokens[:2] in (("php", "vendor/bin/phpunit"),
                                ("php", "./vendor/bin/phpunit"))):
            focused_php.append(gate.get("id", "UNKNOWN"))
        if tokens[:2] == ("npx", "vue-tsc") and "--noEmit" in tokens:
            problems.append("REDUNDANT_NONSTANDARD_TYPECHECK")
        if tokens == NPM_BUILD:
            if gate.get("mandatory") is True:
                builds += 1
            else:
                problems.append("BUILD_NOT_MANDATORY")
        if tokens == NPM_TYPECHECK:
            if gate.get("mandatory") is True:
                typechecks += 1
            else:
                problems.append("TYPECHECK_NOT_MANDATORY")

    if full_php != 1:
        problems.append("FULL_PHP_SUITE_REQUIRED_ONCE")
    if focused_php:
        problems.append("REDUNDANT_FINAL_PHP_TARGETED_TESTS")
    if not _phpunit_covers_all_feature_tests(Path(root)):
        problems.append("PHPUNIT_FEATURE_AND_UNIT_COVERAGE_UNVERIFIED")
    if builds != 1:
        problems.append("VITE_BUILD_REQUIRED_ONCE")
    build_valid, build_covers_ts, standalone_valid = _npm_script_coverage(Path(root))
    if not build_valid:
        problems.append("BUILD_SCRIPT_UNVERIFIED")
    if build_covers_ts:
        if typechecks:
            problems.append("REDUNDANT_FINAL_TYPECHECK")
    else:
        if typechecks != 1:
            problems.append("STANDALONE_TYPECHECK_REQUIRED_IF_BUILD_UNPROVEN")
        elif not standalone_valid:
            problems.append("TYPECHECK_SCRIPT_UNVERIFIED")
    return problems
