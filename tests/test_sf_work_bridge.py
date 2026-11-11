import importlib.util
import sys
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

P = Path(__file__).resolve().parents[1] / "scripts" / "sf_work_bridge.py"
spec = importlib.util.spec_from_file_location("sf_work_bridge", P)
b = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = b
spec.loader.exec_module(b)


class TestBridge(unittest.TestCase):
    def payload(self, **kw):
        p = dict(protocol=b.PROTOCOL, action="STOP", instruction_id="SMOKE-001", report_id="TEST-001",
                 feature="USERS-001", source_feature="USERS-001", base_sha="a" * 40, notes="prueba")
        p.update(kw)
        return p

    def body(self, data):
        import json
        return "\x60\x60\x60sf-work-instruction-v1\n" + json.dumps(data) + "\n\x60\x60\x60"

    def test_parse_stop(self):
        self.assertEqual(b.parse_instruction(self.body(self.payload()))["action"], "STOP")

    def test_reject_shell_action_and_unknown_fields(self):
        with self.assertRaises(b.BridgeError):
            b.parse_instruction(self.body(self.payload(action="SHELL")))
        with self.assertRaises(b.BridgeError):
            b.parse_instruction(self.body(self.payload(shell="rm -rf")))

    def test_reject_bad_sha_feature(self):
        for params in ({"base_sha": "main"}, {"feature": "../../a"}):
            with self.assertRaises(b.BridgeError):
                b.validate_instruction(self.payload(**params))

    def test_reject_non_json(self):
        with self.assertRaises(b.BridgeError):
            b.parse_instruction("\x60\x60\x60sf-work-instruction-v1\n{oops}\n\x60\x60\x60")

    def test_requires_prior_matching_report(self):
        import json
        data = self.payload()
        report = {"protocol": b.REPORT_PROTOCOL, "report_id": data["report_id"],
                  "feature": data["feature"], "sha": data["base_sha"], "kind": "SMOKE"}
        rc = {"id": 9, "body": "\x60\x60\x60sf-oc-report-v1\n" + json.dumps(report, indent=2) + "\n\x60\x60\x60"}
        ic = {"id": 10, "user": {"login": "marioromero"}, "body": self.body(data)}
        class FakeGitHub:
            def main_sha(self): return data["base_sha"]
        with tempfile.TemporaryDirectory() as d:
            with patch.object(b, "STATE", Path(d) / "state.json"):
                args = SimpleNamespace(trusted_login="marioromero", execute=False, all_comments=[rc, ic], timeout=100)
                self.assertTrue(b.handle_comment(ic, {}, FakeGitHub(), args))
                self.assertFalse(b.handle_comment(ic, b.read_state(), FakeGitHub(), args))
                args.all_comments = [ic]
                with self.assertRaises(b.BridgeError):
                    b.handle_comment(ic, {}, FakeGitHub(), args)

    def test_unknown_actor_ignored(self):
        ic = {"id": 11, "user": {"login": "intruder"}, "body": self.body(self.payload())}
        args = SimpleNamespace(trusted_login="marioromero")
        self.assertFalse(b.handle_comment(ic, {}, None, args))


if __name__ == "__main__":
    unittest.main()
