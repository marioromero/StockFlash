# Model selection and benchmark policy — no blind model switching

**Historical model catalog is stale.** Do not hard-code assumed availability of Muse, LongCat, MiMo, Nemotron or any model from previous sessions. Query the installed OpenCode model list when model selection materially matters. Respect provider terms and never expose PII/secrets.

Benchmark with synthetic fixtures and comparable small tasks (UI/contract, price/idempotence test, seeded bug diff review), not repeated full implementations. For each observed model record exact model ID/version, measured provider tokens (input/output/cache if available), wall time, toolcalls, tests actually passed, retries, diff quality and human intervention. Unknown metrics stay null.

Compare total verified cost per feature, not promotional context size. Preserve a fixed model per run unless explicit change and checkpoint; no silent fallback router or quota circumvention. Do not optimize the harness just because a model claims a million-token window.

The next dedicated token audit will capture actual available metrics; no performance percentage can be inferred before a matched baseline exists.
