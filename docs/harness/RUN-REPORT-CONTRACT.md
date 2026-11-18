# RUN-REPORT v1 — historical proposal, not a second live logging system

**Status:** proposed template; NOT implemented as a separate independent pipeline. Mandatory current evidence is already `docs/work/<ID>-VERIFY-*.md`, `<ID>-REVIEW-*.md`, publisher SHA/CI and the SFH1 issue. Do not duplicate these reports in every LLM response.

Proposed record for a future explicitly authorized report parser:
```text
run_id: <id>  feature: <id>  result: DONE|STOP|BLOCKED|FAILED
scope: <approved packet/paths>  fingerprint: <exact tree hash or UNKNOWN>
gates: PASS|FAIL|UNVERIFIED  qa: APPROVED|CHANGES_REQUIRED|UNVERIFIED
git: local=<SHA> remote=<SHA> pushed=yes|no|UNKNOWN
ci: SUCCESS|FAILURE|PENDING|UNVERIFIED (run URL + SHA)
agents: orchestrator/implementer/reviewer/publisher observed or unknown
tests: passed/failed or UNKNOWN   next: <one exact step>
```

Never infer CI or tool use from agent prose. Preserve append-only IDs, UTC timestamps, tree fingerprints including untracked files, artifact links and explicit unknowns; publishing DONE is not technical VERIFIED. Future automation needs parser tests, safe logging and a dedicated approved packet. The token audit protocol is a distinct *observability* stream, not a replacement for feature evidence.
