"""Unit tests for scripts/sf_timeline.py (stdlib only, temp dirs, no network)."""
from __future__ import annotations

import contextlib
import io
import json
import os
import sys
import tempfile
import threading
import unittest
from pathlib import Path
from unittest import mock

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts"))

import sf_timeline as tl  # noqa: E402


class Clock:
    """Deterministic epoch-ms clock for begin/end telemetry calls."""

    def __init__(self, start: int = 1_700_000_000_000) -> None:
        self.value = start

    def __call__(self) -> int:
        return self.value

    def set(self, value: int) -> int:
        self.value = value
        return value


class TimelineTest(unittest.TestCase):
    def setUp(self) -> None:
        self._tmp = tempfile.TemporaryDirectory()
        self.base = Path(self._tmp.name)

    def tearDown(self) -> None:
        self._tmp.cleanup()

    def checkpoint(self, feature: str, data: dict | None = None) -> Path:
        path = self.base / f"{feature}.json"
        path.write_text(json.dumps(data if data is not None else {"packet": feature}), encoding="utf-8")
        return path

    def begin(self, feature: str, stage: str, actor: str = "orchestrator", **kwargs):
        return tl.begin_span(feature, stage, actor, base_dir=self.base, **kwargs)

    def end(self, feature: str, stage: str, actor: str = "orchestrator", status: str = "PASS", **kwargs):
        return tl.end_span(feature, stage, actor, status=status, base_dir=self.base, **kwargs)

    def call_main(self, argv: list[str]) -> tuple[int, str, str]:
        out, err = io.StringIO(), io.StringIO()
        with contextlib.redirect_stdout(out), contextlib.redirect_stderr(err):
            code = tl.main(argv)
        return code, out.getvalue(), err.getvalue()

    def test_normal_multistage_path(self) -> None:
        feature = "TEST-001"
        self.checkpoint(feature)
        clock = Clock()
        stages = ("preflight", "implementation", "verification", "qa", "publication", "ci")
        for index, stage in enumerate(stages):
            start = clock.set(1_000_000 + index * 10_000)
            self.begin(feature, stage, now=clock)
            clock.set(start + 2_000)
            self.end(feature, stage, now=clock)

        events = tl.read_events(feature, base_dir=self.base)
        self.assertEqual(len(events), 12)
        status = tl.build_status(feature, base_dir=self.base)
        self.assertFalse(status["incomplete"])
        self.assertEqual([item["duration_ms"] for item in status["closed"]], [2000] * 6)
        self.assertEqual([item["outcome"] for item in status["closed"]], ["PASS"] * 6)

    def test_two_qa_correction_cycles(self) -> None:
        feature = "TEST-002"
        self.checkpoint(feature)
        clock = Clock()
        sequence = [
            ("qa", "FAIL", 1),
            ("correction", "PASS", 1),
            ("qa", "FAIL", 2),
            ("correction", "PASS", 2),
        ]
        stamp = 2_000_000
        for stage, outcome, attempt in sequence:
            clock.set(stamp)
            self.begin(feature, stage, attempt=attempt, now=clock)
            clock.set(stamp + 500)
            self.end(feature, stage, status=outcome, attempt=attempt, now=clock)
            stamp += 1_000

        status = tl.build_status(feature, base_dir=self.base)
        self.assertEqual(len(status["closed"]), 4)
        summary = tl.build_summary(feature, base_dir=self.base)
        self.assertIn("qa=2", summary)
        self.assertIn("correction=2", summary)
        self.assertIn("qa=1,2", summary)
        self.assertIn("correction=1,2", summary)

    def test_nested_verification_counts_native_once(self) -> None:
        feature = "TEST-003"
        self.checkpoint(feature)
        clock = Clock()
        clock.set(0)
        self.begin(feature, "verification", actor="orchestrator", now=clock)
        clock.set(200)
        self.begin(feature, "verification", actor="verifier", source="native_gate_runner", now=clock)
        clock.set(800)
        self.end(feature, "verification", actor="verifier", source="native_gate_runner", now=clock)
        clock.set(1000)
        self.end(feature, "verification", actor="orchestrator", now=clock)

        summary = tl.build_summary(feature, base_dir=self.base)
        self.assertIn("Ejecuciones sf_verify (native_gate_runner): 1", summary)
        self.assertNotIn("Ejecuciones sf_verify (native_gate_runner): 2", summary)
        self.assertIn("Duracion suma (ms): 1600", summary)
        self.assertIn("Duracion union (ms): 1000", summary)

    def test_interrupted_stage_and_missing_close(self) -> None:
        feature = "TEST-004"
        self.checkpoint(feature)
        clock = Clock()
        clock.set(5_000)
        self.begin(feature, "qa", now=clock)

        status = tl.build_status(feature, base_dir=self.base)
        self.assertTrue(status["incomplete"])
        self.assertEqual(len(status["active"]), 1)
        self.assertEqual(status["closed"], [])
        self.assertGreaterEqual(status["active"][0]["elapsed_ms"], 0)

        with self.assertRaises(tl.SpanNotFound):
            self.end(feature, "implementation", now=clock)
        code, out, _err = self.call_main(["end", feature, "implementation", "--actor", "orchestrator", "--status", "PASS"])
        self.assertEqual(code, 3)
        self.assertIn("SPAN_NOT_FOUND", out)

    def test_malformed_and_truncated_lines(self) -> None:
        feature = "TEST-005"
        self.checkpoint(feature)
        clock = Clock()
        clock.set(10)
        self.begin(feature, "implementation", now=clock)
        clock.set(20)
        self.end(feature, "implementation", now=clock)
        path = self.base / f"{feature}.timeline.jsonl"
        with open(path, "a", encoding="utf-8") as handle:
            handle.write('{"v":1,"feature":"TEST-005"')

        events = tl.read_events(feature, base_dir=self.base)
        self.assertEqual(len(events), 2)
        status = tl.build_status(feature, base_dir=self.base)
        self.assertEqual(status["invalid_lines"], 1)
        summary = tl.build_summary(feature, base_dir=self.base)
        self.assertIn("LINEAS_INVALIDAS: 1", summary)

        clock.set(30)
        self.begin(feature, "qa", now=clock)
        events = tl.read_events(feature, base_dir=self.base)
        self.assertEqual(len(events), 3)
        self.assertEqual(tl.build_status(feature, base_dir=self.base)["invalid_lines"], 1)

    def test_invalid_identifiers_rejected(self) -> None:
        bad_features = ("lowercase", "../X", "A B", "", "AB", "A" * 65, "C:\\x")
        for bad in bad_features:
            with self.assertRaises(ValueError):
                tl.begin_span(bad, "qa", "orchestrator", base_dir=self.base)
            code, out, err = self.call_main(["begin", bad, "qa", "--actor", "orchestrator"])
            self.assertEqual(code, 2)
            if bad:
                self.assertNotIn(bad, out + err)

        feature = "TEST-006"
        with self.assertRaises(ValueError):
            tl.begin_span(feature, "qa", "hacker", base_dir=self.base)
        with self.assertRaises(ValueError):
            tl.begin_span(feature, "SECRET_PROMPT_abc", "orchestrator", base_dir=self.base)
        with self.assertRaises(ValueError):
            tl.begin_span(feature, "qa", "orchestrator", source="polling", base_dir=self.base)
        with self.assertRaises(ValueError):
            self.end(feature, "qa", status="MAYBE")
        self.assertFalse((self.base / f"{feature}.timeline.jsonl").exists())
        code, _out, _err = self.call_main(["status", "lowercase"])
        self.assertEqual(code, 2)
        code, _out, _err = self.call_main(["end", feature, "qa", "--actor", "orchestrator", "--status", "NOPE"])
        self.assertEqual(code, 2)

    def test_checkpoint_preserved_and_unparseable_untouched(self) -> None:
        feature = "TEST-007"
        path = self.checkpoint(
            feature,
            {"packet": feature, "files": ["a"], "attempts": 3, "commit_sha": "deadbeef"},
        )
        clock = Clock()
        clock.set(100)
        self.begin(feature, "preflight", now=clock)

        data = json.loads(path.read_text(encoding="utf-8"))
        self.assertEqual(data["packet"], feature)
        self.assertEqual(data["files"], ["a"])
        self.assertEqual(data["attempts"], 3)
        self.assertEqual(data["commit_sha"], "deadbeef")
        self.assertEqual(set(data["telemetry"]), {"current_stage", "last_event", "updated_at"})
        self.assertEqual(set(data["telemetry"]["last_event"]), {"type", "stage", "actor", "outcome", "ts"})

        broken = self.base / "TEST-008.json"
        broken.write_text("{not json", encoding="utf-8")
        with contextlib.redirect_stderr(io.StringIO()):
            self.begin("TEST-008", "preflight", now=clock)
        self.assertEqual(broken.read_text(encoding="utf-8"), "{not json")

    def test_concurrent_appends(self) -> None:
        feature = "TEST-009"
        self.checkpoint(feature)

        def worker(gate_id: str) -> None:
            for index in range(5):
                tl.record_gate(
                    feature,
                    gate_id,
                    outcome="PASS",
                    duration_ms=index,
                    base_dir=self.base,
                )

        threads = [
            threading.Thread(target=worker, args=("G-a",)),
            threading.Thread(target=worker, args=("G-b",)),
        ]
        for thread in threads:
            thread.start()
        for thread in threads:
            thread.join()

        events = tl.read_events(feature, base_dir=self.base)
        self.assertEqual(len(events), 10)
        self.assertTrue(all(event["type"] == "gate" for event in events))
        summary = tl.build_summary(feature, base_dir=self.base)
        self.assertIn("G-a=5/", summary)
        self.assertIn("G-b=5/", summary)

    def test_wall_clock_skew_is_honest(self) -> None:
        feature = "TEST-010"
        self.checkpoint(feature)
        clock = Clock()
        clock.set(10_000)
        self.begin(feature, "verification", now=clock)
        clock.set(9_000)
        event = self.end(feature, "verification", now=clock)

        self.assertIsNotNone(event)
        self.assertEqual(event["duration_ms"], -1000)
        self.assertLessEqual(event["duration_ms"], 0)
        self.assertIsNotNone(event["wall_skew_ms"])
        status = tl.build_status(feature, base_dir=self.base)
        self.assertEqual(status["closed"][0]["duration_ms"], -1000)
        summary = tl.build_summary(feature, base_dir=self.base)
        self.assertIn("Duracion suma (ms): -1000", summary)
        self.assertIn("Duracion union (ms): 0", summary)
        self.assertIn("DESCONOCIDO", summary)

    def test_schema_and_no_secret_payloads(self) -> None:
        feature = "TEST-011"
        self.checkpoint(feature)
        clock = Clock()
        clock.set(100)
        self.begin(feature, "implementation", now=clock)
        clock.set(200)
        self.end(feature, "implementation", now=clock)
        tl.record_gate(feature, "G1-safe", outcome="PASS", duration_ms=7, base_dir=self.base, now=clock)

        raw = (self.base / f"{feature}.timeline.jsonl").read_text(encoding="utf-8")
        self.assertNotIn("SECRET_PROMPT_abc", raw)
        self.assertNotIn("--danger", raw)
        for line in raw.splitlines():
            event = json.loads(line)
            self.assertEqual(set(event), set(tl.EVENT_KEYS))

        before = len(tl.read_events(feature, base_dir=self.base))
        tl.record_gate(feature, "--danger;rm", outcome="PASS", duration_ms=1, base_dir=self.base)
        self.assertEqual(len(tl.read_events(feature, base_dir=self.base)), before)

        code, out, err = self.call_main(["begin", feature, "qa", "--actor", "orchestrator", "--danger"])
        self.assertEqual(code, 2)
        self.assertNotIn("--danger", out + err)

    def test_status_summary_read_only(self) -> None:
        feature = "TEST-012"
        checkpoint = self.checkpoint(feature)
        clock = Clock()
        clock.set(100)
        self.begin(feature, "qa", now=clock)
        clock.set(200)
        self.end(feature, "qa", now=clock)

        path = self.base / f"{feature}.timeline.jsonl"
        before_bytes = path.read_bytes()
        before_mtime = path.stat().st_mtime_ns
        checkpoint_bytes = checkpoint.read_bytes()

        tl.build_status(feature, base_dir=self.base)
        tl.build_summary(feature, base_dir=self.base)
        previous = os.environ.get("SF_TIMELINE_DIR")
        previous_test = os.environ.get("SF_TIMELINE_TEST")
        os.environ["SF_TIMELINE_DIR"] = str(self.base)
        os.environ["SF_TIMELINE_TEST"] = "1"
        try:
            self.call_main(["status", feature])
            self.call_main(["summary", feature])
        finally:
            if previous is None:
                os.environ.pop("SF_TIMELINE_DIR", None)
            else:
                os.environ["SF_TIMELINE_DIR"] = previous
            if previous_test is None:
                os.environ.pop("SF_TIMELINE_TEST", None)
            else:
                os.environ["SF_TIMELINE_TEST"] = previous_test

        self.assertEqual(path.read_bytes(), before_bytes)
        self.assertEqual(path.stat().st_mtime_ns, before_mtime)
        self.assertEqual(checkpoint.read_bytes(), checkpoint_bytes)
        self.assertFalse((self.base / f"{feature}.timeline.lock").exists())

    def test_checkpoint_conflict_does_not_replace(self) -> None:
        feature = "TEST-014"
        checkpoint = self.checkpoint(feature, {"packet": feature, "files": ["a"]})
        event = tl._event(
            feature=feature,
            etype="begin",
            stage="qa",
            actor="orchestrator",
            span="abcdef01",
            epoch_ms=100,
            mono_ms=0,
            attempt=None,
            outcome=None,
            source="observed_stage_boundary",
        )
        external = {"packet": feature, "external": True, "files": ["a"]}
        real_write_text = Path.write_text

        def racing_write(inner_self, data, *args, **kwargs):
            result = real_write_text(inner_self, data, *args, **kwargs)
            if str(inner_self).endswith(".tmp"):
                checkpoint.write_text(json.dumps(external), encoding="utf-8")
            return result

        err = io.StringIO()
        with mock.patch.object(Path, "write_text", autospec=True, side_effect=racing_write), \
                mock.patch("sf_timeline.os.replace") as replaced, \
                contextlib.redirect_stderr(err):
            updated = tl._update_checkpoint(feature, event, base_dir=self.base)

        self.assertFalse(updated)
        replaced.assert_not_called()
        self.assertEqual(json.loads(checkpoint.read_text(encoding="utf-8")), external)
        self.assertIn("TELEMETRY_CHECKPOINT_CONFLICT", err.getvalue())
        self.assertEqual(list(self.base.glob("*.tmp")), [])

    def test_verifier_native_integration(self) -> None:
        import sf_verify

        feature = "TEST-001"
        self.checkpoint(feature)
        previous_env = os.environ.get("SF_TIMELINE_DIR")
        previous_test = os.environ.get("SF_TIMELINE_TEST")
        os.environ["SF_TIMELINE_DIR"] = str(self.base)
        os.environ["SF_TIMELINE_TEST"] = "1"
        previous_gates = sf_verify.GATES_BY_PACKET.get(feature)
        previous_write = sf_verify.write_report
        previous_argv = list(sys.argv)

        sf_verify.GATES_BY_PACKET[feature] = [
            {
                "id": "T1-synthetic",
                "kind": "shell",
                "cmd": [sys.executable, "-c", "pass"],
                "mandatory": True,
                "timeout_s": 60,
            }
        ]
        sf_verify.write_report = lambda packet, results, verdict, guide, roadmap: self.base / f"{packet}.report.md"
        sys.argv = ["sf_verify.py", feature]
        try:
            with contextlib.redirect_stdout(io.StringIO()):
                code = sf_verify.main()
        finally:
            sys.argv[:] = previous_argv
            sf_verify.write_report = previous_write
            if previous_gates is None:
                sf_verify.GATES_BY_PACKET.pop(feature, None)
            else:
                sf_verify.GATES_BY_PACKET[feature] = previous_gates
            if previous_env is None:
                os.environ.pop("SF_TIMELINE_DIR", None)
            else:
                os.environ["SF_TIMELINE_DIR"] = previous_env
            if previous_test is None:
                os.environ.pop("SF_TIMELINE_TEST", None)
            else:
                os.environ["SF_TIMELINE_TEST"] = previous_test

        self.assertEqual(code, 0)
        events = tl.read_events(feature, base_dir=self.base)
        native = [
            event for event in events
            if event["type"] == "begin"
            and event["stage"] == "verification"
            and event["source"] == "native_gate_runner"
        ]
        gates = [event for event in events if event["type"] == "gate"]
        self.assertEqual(len(native), 1)
        self.assertEqual([event["gate_id"] for event in gates], ["T1-synthetic"])
        self.assertEqual(gates[0]["outcome"], "PASS")

    def test_orphaned_native_span_not_closed_by_verifier(self) -> None:
        import sf_verify

        feature = "TEST-001"
        self.checkpoint(feature)
        clock = Clock()
        clock.set(100)
        orphan = tl.begin_span(
            feature,
            "verification",
            "verifier",
            source="native_gate_runner",
            base_dir=self.base,
            now=clock,
        )
        self.assertIsNotNone(orphan)

        previous_env = os.environ.get("SF_TIMELINE_DIR")
        previous_test = os.environ.get("SF_TIMELINE_TEST")
        os.environ["SF_TIMELINE_DIR"] = str(self.base)
        os.environ["SF_TIMELINE_TEST"] = "1"
        previous_gates = sf_verify.GATES_BY_PACKET.get(feature)
        previous_write = sf_verify.write_report
        previous_argv = list(sys.argv)

        sf_verify.GATES_BY_PACKET[feature] = [
            {
                "id": "T1-synthetic",
                "kind": "shell",
                "cmd": [sys.executable, "-c", "pass"],
                "mandatory": True,
                "timeout_s": 60,
            }
        ]
        sf_verify.write_report = lambda packet, results, verdict, guide, roadmap: self.base / f"{packet}.report.md"
        sys.argv = ["sf_verify.py", feature]

        def run_once() -> int:
            with contextlib.redirect_stdout(io.StringIO()):
                return sf_verify.main()

        try:
            self.assertEqual(run_once(), 0)
            first = tl.build_status(feature, base_dir=self.base)
            self.assertEqual(len(first["active"]), 1)
            self.assertEqual(first["active"][0]["span"], orphan["span"])
            self.assertEqual(len(first["closed"]), 1)
            self.assertNotEqual(first["closed"][0]["span"], orphan["span"])

            self.assertEqual(run_once(), 0)
            second = tl.build_status(feature, base_dir=self.base)
            self.assertEqual(len(second["active"]), 1)
            self.assertEqual(second["active"][0]["span"], orphan["span"])
            self.assertEqual(len(second["closed"]), 2)
            self.assertTrue(all(item["span"] != orphan["span"] for item in second["closed"]))
            summary = tl.build_summary(feature, base_dir=self.base)
            self.assertIn("Ejecuciones sf_verify (native_gate_runner): 2", summary)
        finally:
            sys.argv[:] = previous_argv
            sf_verify.write_report = previous_write
            if previous_gates is None:
                sf_verify.GATES_BY_PACKET.pop(feature, None)
            else:
                sf_verify.GATES_BY_PACKET[feature] = previous_gates
            if previous_env is None:
                os.environ.pop("SF_TIMELINE_DIR", None)
            else:
                os.environ["SF_TIMELINE_DIR"] = previous_env
            if previous_test is None:
                os.environ.pop("SF_TIMELINE_TEST", None)
            else:
                os.environ["SF_TIMELINE_TEST"] = previous_test

    def test_corrupt_scalar_event_is_ignored(self) -> None:
        feature = "TEST-015"
        self.checkpoint(feature)
        clock = Clock()
        clock.set(100)
        self.begin(feature, "implementation", now=clock)
        clock.set(200)
        self.end(feature, "implementation", now=clock)

        bad = {
            "v": 1,
            "feature": feature,
            "type": "begin",
            "stage": "qa",
            "actor": "orchestrator",
            "span": "deadbeef",
            "ts": "2026-01-01T00:00:00.000Z",
            "epoch_ms": 300,
            "mono_ms": None,
            "attempt": "x",
            "outcome": None,
            "source": "observed_stage_boundary",
            "gate_id": None,
            "duration_ms": "oops",
            "wall_skew_ms": None,
        }
        path = self.base / f"{feature}.timeline.jsonl"
        with open(path, "a", encoding="utf-8") as handle:
            handle.write(json.dumps(bad) + "\n")

        events = tl.read_events(feature, base_dir=self.base)
        self.assertEqual(len(events), 2)
        status = tl.build_status(feature, base_dir=self.base)
        self.assertGreaterEqual(status["invalid_lines"], 1)
        summary = tl.build_summary(feature, base_dir=self.base)
        self.assertIsInstance(summary, str)
        self.assertIn("LINEAS_INVALIDAS: 1", summary)


if __name__ == "__main__":
    unittest.main()
