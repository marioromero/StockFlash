---
description: Run the deterministic mandatory gates for one approved feature without QA or editing.
agent: general
---

For `$ARGUMENTS`, execute **once per candidate state**: `py -3 scripts/sf_verify.py $ARGUMENTS`. If no `GATES_BY_PACKET` definition exists, report GATES_FAIL and define required gates under approved scope rather than improvising or bypassing them.

This runner alone owns the formal GATES_PASS/FAIL evidence. It is not the independent reviewer, publisher or ChatGPT VERIFIED authority. All mandatory tests (including the full suite where declared), typecheck/build, contracts and security checks must run exactly as specified, never be replaced by prior stale reports. During coding, targeted tests are separate and not substitutes for final gate.

Output only bounded status/evidence path to the model; keep full safe log in deterministic report. Never read .env/credentials, run real DB commands, deploy, commit/push, or leave a permanent dev server. Temporary processes must be closed. English internal instructions; status names remain unchanged.
