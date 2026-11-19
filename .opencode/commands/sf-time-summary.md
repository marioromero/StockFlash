---
description: On-demand read-only summary of StockFlash feature stage durations, attempts and native gate runs.
agent: plan
---

This command is **manual/on-demand only**. It does NOT create telemetry, trigger a scheduled audit, load previous conversations, run tests, run Git synchronization, call subagents, or inspect OpenCode session history.

For `$ARGUMENTS`, require **exactly one** feature ID matching `^[A-Z0-9][A-Z0-9-]{2,63}$`; otherwise respond `INVALID_FEATURE_ID` without running anything. From repository root, run exactly once:

```powershell
py -3 scripts/sf_timeline.py summary $ARGUMENTS
```

Show the concise existing Spanish summary of stage timing, attempts, status, and `sf_verify` gate counts/durations. Distinguish sum vs union and keep unknown model/queue time marked unknown. The script reads **only local ignored** `docs/work/.run/<ID>.timeline.jsonl`. Missing data means `SIN_DATOS`, not success. No automatic invocation during normal `/sf-run`: stage boundary logging is a separate, already-approved behavior.
