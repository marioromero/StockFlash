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
        self.report.write_text("Veredicto: GATES_FAIL\n", encoding="utf-8")
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


if __name__ == "__main__":
    unittest.main()
