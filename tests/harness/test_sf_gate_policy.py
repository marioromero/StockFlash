"""HQAEFF-002 synthetic-only final-gate plan regression tests."""
from __future__ import annotations

import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts"))
import sf_gate_policy as gp  # noqa: E402


def gate(name: str, cmd: list[str], mandatory: bool = True) -> dict:
    return {"id": name, "kind": "shell", "cmd": cmd, "mandatory": mandatory, "timeout_s": 90}


class GatePlanTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name)
        self.xml = self.root / "phpunit.xml"
        self.xml.write_text(
            '<phpunit><testsuites><testsuite name="Unit">'
            '<directory>tests/Unit</directory></testsuite>'
            '<testsuite name="Feature"><directory>tests/Feature</directory></testsuite>'
            '</testsuites></phpunit>', encoding="utf-8"
        )
        self.pkg = self.root / "package.json"
        self.write_scripts("vue-tsc --noEmit && vite build")

    def tearDown(self):
        self.tmp.cleanup()

    def write_scripts(self, build):
        self.pkg.write_text(json.dumps({"scripts": {
            "typecheck": "vue-tsc --noEmit", "build": build,
        }}), encoding="utf-8")

    def plan(self):
        return [
            gate("contract", ["py", "-3", "scripts/check_contracts.py"]),
            gate("suite", ["php", "artisan", "test"]),
            gate("build", ["npm", "run", "build"]),
        ]

    def verify(self, plan):
        return gp.check_final_plan("FUTURE-001", plan, self.root)

    def test_valid_future_packet_uses_one_full_php_and_typed_build(self):
        self.assertEqual(self.verify(self.plan()), [])

    def test_inventory_targeted_gate_is_rejected_but_still_unmodified(self):
        plan = self.plan()
        extra = gate("inventory", ["php", "artisan", "test", "tests/Feature/Inventory"])
        plan.insert(1, extra)
        self.assertIn("REDUNDANT_FINAL_PHP_TARGETED_TESTS", self.verify(plan))
        self.assertEqual(plan[1], extra)

    def test_multiple_php_targeted_gate_forms_rejected(self):
        for cmd in [
            ["php", "artisan", "test", "tests/Feature/Inventory"],
            ["php", "artisan", "test", "--filter", "StockLedger"],
            ["php", "artisan", "test", "tests/Feature/Core"],
        ]:
            with self.subTest(cmd=cmd):
                self.assertIn("REDUNDANT_FINAL_PHP_TARGETED_TESTS", self.verify(self.plan() + [gate("target", cmd)]))

    def test_missing_or_optional_full_suite_rejected(self):
        self.assertIn("FULL_PHP_SUITE_REQUIRED_ONCE", self.verify(self.plan()[0:1] + self.plan()[2:]))
        plan = self.plan()
        plan[1]["mandatory"] = False
        self.assertIn("FULL_PHP_SUITE_REQUIRED_ONCE", self.verify(plan))

    def test_duplicate_full_suite_rejected(self):
        self.assertIn("FULL_PHP_SUITE_REQUIRED_ONCE", self.verify(self.plan() + [gate("duplicate", ["php", "artisan", "test"])]))

    def test_missing_or_optional_build_rejected(self):
        self.assertIn("VITE_BUILD_REQUIRED_ONCE", self.verify(self.plan()[:-1]))
        plan = self.plan()
        plan[-1]["mandatory"] = False
        self.assertIn("BUILD_NOT_MANDATORY", self.verify(plan))

    def test_duplicated_standalone_typecheck_rejected_when_build_typechecks(self):
        plan = self.plan() + [gate("ts", ["npm", "run", "typecheck"])]
        self.assertIn("REDUNDANT_FINAL_TYPECHECK", self.verify(plan))

    def test_standalone_typecheck_required_if_npm_build_changes(self):
        self.write_scripts("vite build")
        self.assertIn("STANDALONE_TYPECHECK_REQUIRED_IF_BUILD_UNPROVEN", self.verify(self.plan()))
        self.assertEqual(self.verify(self.plan() + [gate("ts", ["npm", "run", "typecheck"])]), [])

    def test_unknown_build_script_must_not_skip_typecheck(self):
        self.write_scripts("vue-tsc --noEmit; vite build")
        self.assertIn("STANDALONE_TYPECHECK_REQUIRED_IF_BUILD_UNPROVEN", self.verify(self.plan()))

    def test_missing_phpunit_coverage_fails_closed(self):
        self.xml.write_text('<phpunit><testsuites><testsuite><directory>tests/Unit</directory></testsuite></testsuites></phpunit>', encoding="utf-8")
        self.assertIn("PHPUNIT_FEATURE_AND_UNIT_COVERAGE_UNVERIFIED", self.verify(self.plan()))

    def test_corrupt_phpunit_fails_closed(self):
        self.xml.write_text("<broken>", encoding="utf-8")
        self.assertIn("PHPUNIT_FEATURE_AND_UNIT_COVERAGE_UNVERIFIED", self.verify(self.plan()))

    def test_historic_packet_keeps_exact_approved_gates(self):
        original = self.plan() + [gate("legacy-inventory", ["php", "artisan", "test", "tests/Feature/Inventory"])]
        self.assertEqual(gp.check_final_plan("INVTRANSFER-001", original, self.root), [])
        self.assertEqual(len(original), 4)

    def test_existing_verifier_test_fixtures_not_broken(self):
        self.assertEqual(gp.check_final_plan("TEST-001", [gate("one", ["python", "-c", "pass"])], self.root), [])
        self.assertEqual(gp.check_final_plan("DEMO-001", [gate("one", ["python", "-c", "pass"])], self.root), [])

    def test_malformed_new_plan_rejected(self):
        self.assertIn("INVALID_GATE_LIST", self.verify([]))
        self.assertIn("INVALID_SHELL_COMMAND", self.verify(self.plan() + [
            {"id": "bad", "kind": "shell", "cmd": "php artisan test", "mandatory": True},
        ]))

    def test_repo_phpunit_and_package_scripts_are_covered(self):
        self.assertTrue(gp._phpunit_covers_all_feature_tests(ROOT))
        self.assertTrue(gp._npm_build_includes_typecheck(ROOT))


    def test_new_invalid_plan_is_blocked_by_real_sf_verify_before_execution(self):
        import contextlib
        import io
        from unittest import mock
        import sf_verify

        new_id = "FUTURE-001"
        invalid = self.plan() + [
            gate("inventory", ["php", "artisan", "test", "tests/Feature/Inventory"]),
        ]
        output = io.StringIO()
        with (mock.patch.dict(sf_verify.GATES_BY_PACKET, {new_id: invalid}),
              mock.patch.object(sys, "argv", ["sf_verify.py", new_id]),
              mock.patch.object(sf_verify, "run_shell", side_effect=AssertionError("SHOULD_NOT_RUN"))):
            with contextlib.redirect_stdout(output):
                self.assertEqual(sf_verify.main(), 1)
        self.assertIn("REDUNDANT_FINAL_PHP_TARGETED_TESTS", output.getvalue())

    def test_all_existing_real_packets_remain_grandfathered(self):
        import sf_verify

        self.assertTrue(set(sf_verify.GATES_BY_PACKET).issubset(gp.HISTORICAL_PACKETS))
        for packet, existing_gates in sf_verify.GATES_BY_PACKET.items():
            self.assertEqual(gp.check_final_plan(packet, existing_gates, self.root), [])


    def test_noop_build_and_noop_typecheck_never_authorize_coverage(self):
        self.pkg.write_text(json.dumps({"scripts": {
            "build": "echo build", "typecheck": "echo skipped",
        }}), encoding="utf-8")
        failures = self.verify(self.plan() + [gate("ts", ["npm", "run", "typecheck"])])
        self.assertIn("BUILD_SCRIPT_UNVERIFIED", failures)
        self.assertIn("TYPECHECK_SCRIPT_UNVERIFIED", failures)

    def test_real_vite_build_with_fake_typecheck_rejected(self):
        self.pkg.write_text(json.dumps({"scripts": {
            "build": "vite build", "typecheck": "echo skipped",
        }}), encoding="utf-8")
        self.assertIn("TYPECHECK_SCRIPT_UNVERIFIED",
                      self.verify(self.plan() + [gate("ts", ["npm", "run", "typecheck"])]))

    def test_unknown_build_is_rejected_even_with_real_standalone_typecheck(self):
        self.write_scripts("echo build")
        self.assertIn("BUILD_SCRIPT_UNVERIFIED",
                      self.verify(self.plan() + [gate("ts", ["npm", "run", "typecheck"])]))

    def test_phpunit_excludes_inventory_is_not_full_suite(self):
        self.xml.write_text(
            '<phpunit><testsuites><testsuite name="Unit">'
            '<directory>tests/Unit</directory></testsuite>'
            '<testsuite name="Feature"><directory>tests/Feature</directory>'
            '<exclude>tests/Feature/Inventory</exclude></testsuite>'
            '</testsuites></phpunit>', encoding="utf-8"
        )
        self.assertIn("PHPUNIT_FEATURE_AND_UNIT_COVERAGE_UNVERIFIED", self.verify(self.plan()))

    def test_phpunit_exclude_directory_and_source_exclude_fail_closed(self):
        for xml in [
            '<phpunit><testsuites><testsuite><directory>tests/Feature</directory>'
            '<exclude><directory>tests/Feature/Inventory</directory></exclude></testsuite>'
            '<testsuite><directory>tests/Unit</directory></testsuite></testsuites></phpunit>',
            '<phpunit><source><exclude><directory>app</directory></exclude></source>'
            '<testsuites><testsuite><directory>tests/Feature</directory></testsuite>'
            '<testsuite><directory>tests/Unit</directory></testsuite></testsuites></phpunit>',
        ]:
            with self.subTest(xml=xml[:80]):
                self.xml.write_text(xml, encoding="utf-8")
                self.assertIn("PHPUNIT_FEATURE_AND_UNIT_COVERAGE_UNVERIFIED", self.verify(self.plan()))

    def test_phpunit_filtered_suffix_cannot_disguise_empty_tests(self):
        self.xml.write_text(
            '<phpunit><testsuites><testsuite><directory suffix=".txt">tests/Feature</directory>'
            '</testsuite><testsuite><directory>tests/Unit</directory></testsuite>'
            '</testsuites></phpunit>', encoding="utf-8"
        )
        self.assertIn("PHPUNIT_FEATURE_AND_UNIT_COVERAGE_UNVERIFIED", self.verify(self.plan()))

    def test_phpunit_standard_php_suffix_still_accepted(self):
        self.xml.write_text(
            '<phpunit><testsuites><testsuite><directory suffix=".php">tests/Feature</directory>'
            '</testsuite><testsuite><directory>tests/Unit</directory></testsuite>'
            '</testsuites></phpunit>', encoding="utf-8"
        )
        self.assertEqual(self.verify(self.plan()), [])

    def test_nonstandard_phpunit_and_npx_vue_tsc_rejected_as_duplicate_work(self):
        self.assertIn("REDUNDANT_FINAL_PHP_TARGETED_TESTS", self.verify(
            self.plan() + [gate("redundant", ["vendor/bin/phpunit", "tests/Feature/Inventory"])]))
        self.assertIn("REDUNDANT_FINAL_PHP_TARGETED_TESTS", self.verify(
            self.plan() + [gate("redundant", ["php", "vendor/bin/phpunit", "--filter", "Stock"])]))
        self.assertIn("REDUNDANT_NONSTANDARD_TYPECHECK", self.verify(
            self.plan() + [gate("redundant", ["npx", "vue-tsc", "--noEmit"])]))

    def test_missing_or_malformed_package_scripts_fail_closed(self):
        self.pkg.write_text("not-json", encoding="utf-8")
        self.assertIn("BUILD_SCRIPT_UNVERIFIED", self.verify(self.plan()))
        self.pkg.write_text("[]", encoding="utf-8")
        self.assertIn("BUILD_SCRIPT_UNVERIFIED", self.verify(self.plan()))


if __name__ == "__main__":
    unittest.main()
