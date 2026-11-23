"""Executable regression tests for conservative gate evidence reuse."""
from __future__ import annotations

import json
import subprocess
import sys
import tempfile
import time
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts"))
import sf_evidence as ev  # noqa: E402


class EvidenceReuseTest(unittest.TestCase):
    feature = "DEMO-001"

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.git("init", "-q", "-b", "main")
        (self.root / "src").mkdir()
        (self.root / "src" / "a.php").write_text("first\n", encoding="utf-8")
        (self.root / "docs" / "work").mkdir(parents=True)
        self.git("add", "--", "src/a.php")
        self.git("-c", "user.name=QA", "-c", "user.email=qa@example.invalid",
                 "commit", "-q", "-m", "fixture")
        self.report = self.root / "docs" / "work" / "DEMO-001-VERIFY-20261119-180000.md"
        self.report.write_text("Veredicto: GATES_PASS\n", encoding="utf-8")

    def tearDown(self):
        self.temp.cleanup()

    def git(self, *args):
        subprocess.run(["git", "-C", str(self.root), *args],
                       check=True, capture_output=True, timeout=15)

    def stamp(self):
        before = ev.fingerprint(self.feature, self.root)
        self.assertTrue(ev.record_success(self.feature, before, self.report, self.root))
        return before

    def check(self, **kw):
        return ev.check(self.feature, self.root, **kw)

    def test_no_proof_runs_gates(self):
        self.assertEqual(self.check()[1], "NO_VALID_PROOF")

    def test_exact_unchanged_tree_reuses_existing_real_report(self):
        self.stamp()
        usable, reason, name = self.check()
        self.assertTrue(usable)
        self.assertEqual(reason, "REUSE_VALID")
        self.assertEqual(name, self.report.name)

    def test_review_report_does_not_invalidate_source_fingerprint(self):
        self.stamp()
        review = self.root / "docs" / "work" / "DEMO-001-REVIEW-20261119-180100.md"
        review.write_text("Independent review\n", encoding="utf-8")
        self.assertTrue(self.check()[0])

    def test_four_and_six_digit_review_reports_do_not_invalidate_proof(self):
        before = self.stamp()
        for clock in ("1745", "174500"):
            with self.subTest(clock=clock):
                review = self.root / "docs" / "work" / f"DEMO-001-REVIEW-20261120-{clock}.md"
                review.write_text("Independent QA notes\\n", encoding="utf-8")
                self.assertEqual(ev.fingerprint(self.feature, self.root), before)
                self.assertEqual(self.check(), (True, "REUSE_VALID", self.report.name))

    def test_review_exclusion_never_authorizes_gate_evidence(self):
        before = self.stamp()
        review = self.root / "docs" / "work" / "DEMO-001-REVIEW-20261120-1745.md"
        review.write_text("Veredicto: GATES_PASS\\n", encoding="utf-8")
        self.assertFalse(ev.record_success(self.feature, before, review, self.root))
        proof_path = ev._cache_path(self.feature, self.root)
        proof = json.loads(proof_path.read_text(encoding="utf-8"))
        proof["report"] = review.name
        proof_path.write_text(json.dumps(proof), encoding="utf-8")
        self.assertEqual(self.check()[1], "PROOF_REPORT_INVALID")

    def test_other_feature_and_malformed_review_names_still_invalidate(self):
        before = self.stamp()
        for name in (
            "OTHER-001-REVIEW-20261120-1745.md",
            "OTHER-001-REVIEW-20261120-174500.md",
            "DEMO-001-REVIEW-20261120-174.md",
            "DEMO-001-REVIEW-20261120-174500-extra.md",
            "DEMO-001-REVIEW-20261120-1745.txt",
        ):
            with self.subTest(name=name):
                path = self.root / "docs" / "work" / name
                path.write_text("Unexpected source-visible artifact\\n", encoding="utf-8")
                self.assertEqual(self.check()[1], "SOURCE_CHANGED")
                path.unlink()
                self.assertEqual(ev.fingerprint(self.feature, self.root), before)
                self.assertEqual(self.check()[1], "REUSE_VALID")

    def test_changed_code_or_untracked_test_invalidates(self):
        self.stamp()
        (self.root / "src" / "a.php").write_text("second\n", encoding="utf-8")
        self.assertEqual(self.check()[1], "SOURCE_CHANGED")
        (self.root / "src" / "a.php").write_text("first\n", encoding="utf-8")
        (self.root / "src" / "new.php").write_text("untracked\n", encoding="utf-8")
        self.assertEqual(self.check()[1], "SOURCE_CHANGED")

    def test_changed_head_invalidates_even_when_code_unchanged(self):
        self.stamp()
        self.git("-c", "user.name=QA", "-c", "user.email=qa@example.invalid",
                 "commit", "-q", "--allow-empty", "-m", "new-head")
        self.assertEqual(self.check()[1], "SOURCE_CHANGED")

    def test_report_tampering_invalidates(self):
        self.stamp()
        # Keep PASS verdict to prove the *hash* check detects changes too.
        self.report.write_text("Veredicto: GATES_PASS\nUnreviewed change\n", encoding="utf-8")
        self.assertEqual(self.check()[1], "PROOF_REPORT_CHANGED")

    def test_missing_report_invalidates(self):
        self.stamp()
        self.report.unlink()
        self.assertEqual(self.check()[1], "PROOF_REPORT_MISSING")

    def test_proof_expiry_invalidates(self):
        self.stamp()
        data = json.loads(ev._cache_path(self.feature, self.root).read_text())
        self.assertEqual(self.check(now=data["created_at"] + ev.MAX_AGE_SECONDS + 1)[1], "PROOF_EXPIRED")

    def test_second_attempt_invalidates_prior_pass(self):
        self.stamp()
        ev.invalidate(self.feature, self.root)
        self.assertEqual(self.check()[1], "NO_VALID_PROOF")

    def test_source_mutates_during_gates_never_stamped(self):
        before = ev.fingerprint(self.feature, self.root)
        (self.root / "src" / "a.php").write_text("changed in gate\n", encoding="utf-8")
        self.assertFalse(ev.record_success(self.feature, before, self.report, self.root))
        self.assertEqual(self.check()[1], "NO_VALID_PROOF")

    def test_forged_report_path_rejected(self):
        self.stamp()
        path = ev._cache_path(self.feature, self.root)
        proof = json.loads(path.read_text())
        proof["report"] = "../.env"
        path.write_text(json.dumps(proof), encoding="utf-8")
        self.assertEqual(self.check()[1], "PROOF_REPORT_INVALID")

    def test_untracked_env_is_not_hashed_or_disclosed(self):
        self.stamp()
        (self.root / ".env").write_text("SECRET=NEVER_READ\n", encoding="utf-8")
        # This fixture has no .gitignore. Any visible real .env forces no reuse.
        self.assertEqual(self.check()[1], "SOURCE_FINGERPRINT_UNAVAILABLE")

    def test_invalid_feature_rejected(self):
        with self.assertRaises(ValueError):
            ev.fingerprint("../bad", self.root)


    def test_real_verifier_cli_runs_once_then_reuses_only_unchanged_candidate(self):
        """Integration: real main() gates, but one synthetic runner with no PHP/Node."""
        import contextlib
        import io
        import os
        from unittest import mock
        import sf_verify

        executions = []
        gate = {"id": "Z1-synthetic", "kind": "shell",
                "cmd": ["python", "-c", "pass"], "timeout_s": 10, "mandatory": True}

        def fake_shell(_cmd, _timeout):
            executions.append("gate")
            return {"cmd": "synthetic", "exit": 0, "ok": True,
                    "elapsed_s": 0.001, "stdout": "", "stderr": ""}

        def fake_report(_feature, _results, verdict, _guide, _roadmap):
            self.report.write_text("Veredicto: " + verdict + "\n", encoding="utf-8")
            return self.report

        guide = {"label": "GUIA", "status": "GUIA_ACTUALIZADA"}
        roadmap = {"label": "ROADMAP", "status": "ROADMAP_ACTUALIZADA"}
        with (mock.patch.object(sf_verify, "ROOT", self.root),
              mock.patch.dict(sf_verify.GATES_BY_PACKET, {self.feature: [gate]}),
              mock.patch.object(sf_verify, "run_shell", side_effect=fake_shell),
              mock.patch.object(sf_verify, "write_report", side_effect=fake_report),
              mock.patch.object(sf_verify, "guide_handoff", return_value=guide),
              mock.patch.object(sf_verify, "roadmap_handoff", return_value=roadmap),
              mock.patch.dict(os.environ, {
                  "SF_TIMELINE_TEST": "1",
                  "SF_TIMELINE_DIR": str(self.root / "docs" / "work" / ".run"),
              }),
              mock.patch.object(sys, "argv", ["sf_verify.py", self.feature, "--reuse-evidence"])):
            output = io.StringIO()
            with contextlib.redirect_stdout(output):
                self.assertEqual(sf_verify.main(), 0)
                self.assertEqual(sf_verify.main(), 0)
            self.assertEqual(len(executions), 1)
            self.assertIn("REUSED_PREVIOUS_EXECUTION", output.getvalue())
            (self.root / "src" / "a.php").write_text("changed\n", encoding="utf-8")
            with contextlib.redirect_stdout(io.StringIO()):
                self.assertEqual(sf_verify.main(), 0)
            self.assertEqual(len(executions), 2)


    def test_nonfinite_proof_time_is_rejected(self):
        self.stamp()
        path = ev._cache_path(self.feature, self.root)
        for bogus in [float("nan"), float("inf"), -float("inf"), 10**500, False]:
            with self.subTest(bogus=repr(bogus)):
                proof = json.loads(path.read_text())
                proof["created_at"] = bogus
                path.write_text(json.dumps(proof), encoding="utf-8")
                self.assertFalse(self.check(now=time.time() + 10**9)[0])
        self.assertEqual(self.check(now=float("nan"))[1], "PROOF_TIME_INVALID")

    def test_forged_hash_of_failed_report_is_not_reusable(self):
        import hashlib

        self.stamp()
        self.report.write_text(
            "Veredicto: **GATES_FAIL** (GATES_PASS/GATES_FAIL; nunca VERIFIED).\n",
            encoding="utf-8",
        )
        path = ev._cache_path(self.feature, self.root)
        proof = json.loads(path.read_text())
        proof["report_sha256"] = hashlib.sha256(self.report.read_bytes()).hexdigest()
        path.write_text(json.dumps(proof), encoding="utf-8")
        self.assertEqual(self.check()[1], "PROOF_REPORT_NOT_PASS")

    def test_report_rejects_stray_pass_text_or_conflicting_verdict(self):
        self.stamp()
        path = ev._cache_path(self.feature, self.root)
        for body in [
            "Gate stdout: GATES_PASS\nVeredicto: GATES_FAIL\n",
            "Veredicto: GATES_PASS\nVeredicto: GATES_FAIL\n",
            "Veredicto: GATES_PASSED\n",
        ]:
            with self.subTest(body=body):
                self.report.write_text(body, encoding="utf-8")
                proof = json.loads(path.read_text())
                import hashlib
                proof["report_sha256"] = hashlib.sha256(self.report.read_bytes()).hexdigest()
                path.write_text(json.dumps(proof), encoding="utf-8")
                self.assertEqual(self.check()[1], "PROOF_REPORT_NOT_PASS")

    def test_proof_symlink_is_never_read(self):
        self.stamp()
        proof_path = ev._cache_path(self.feature, self.root)
        original = proof_path.read_text(encoding="utf-8")
        proof_path.unlink()
        target = self.root / ".env"
        target.write_text("DO_NOT_READ=YES\n", encoding="utf-8")
        try:
            proof_path.symlink_to(target)
        except (NotImplementedError, OSError):
            self.skipTest("Symlink creation unavailable on this platform")
        self.assertEqual(self.check()[1], "PROOF_FILE_SYMLINK")
        proof_path.unlink()
        proof_path.write_text(original, encoding="utf-8")


if __name__ == "__main__":
    unittest.main()
