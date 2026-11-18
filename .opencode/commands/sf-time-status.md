---
description: On-demand read-only stage status for a StockFlash feature, using existing local telemetry.
agent: plan
---

This command is **manual/on-demand only**. It does NOT create telemetry, trigger a scheduled audit, load previous conversations, run tests, run Git synchronization, call subagents, or inspect OpenCode session history.

For `$ARGUMENTS`, require **exactly one** feature ID matching `^[A-Z0-9][A-Z0-9-]{2,63}$`; otherwise respond `INVALID_FEATURE_ID` without running anything. From repository root, run exactly once:

```powershell
py -3 scripts/sf_timeline.py status $ARGUMENTS
```

Display the bounded output as-is, with a one-line Spanish explanation only if necessary. The script reads **only local ignored** `docs/work/.run/<ID>.timeline.jsonl` for its status. Missing data means `SIN_DATOS`, not success. No automatic invocation during normal `/sf-run`: stage boundary logging is a separate, already-approved behavior.
