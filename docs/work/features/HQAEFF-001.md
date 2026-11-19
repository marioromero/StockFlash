# HQAEFF-001 — QA efficiency guard Phase A

status: DRAFT
owner_authorization: explicit_2026-11-19
execution_mode: ISOLATED_PULL_REQUEST
publication_mode: REVIEW_BEFORE_MERGE
active: NO; do not alter CURRENT.md or interrupt the active INVREORDER-001 packet
scope: harness-only; no product/business decisions

## Deliverables
1. Local deterministic, fail-closed short-lived exact-source gate PASS proof: scripts/sf_evidence.py and opt-in --reuse-evidence in scripts/sf_verify.py.
2. Regression tests: tests/harness/test_sf_evidence.py alongside existing telemetry tests.
3. Remove obsolete routine HOBS future-collector instructions from agents; preserve historical audit artifacts. Add QA-EFFICIENCY-GUARD.md.

## Release
Code stays on an isolated PR until independent adversarial QA and all available CI jobs succeed on its exact head. Do not merge automatically while another product feature is active. The existing feature gates, publisher, QA and exact-SHA CI remain mandatory. No deploy or real DB operations.

## Acceptance
No/cache-corrupted/stale/fake proof, changed tracked/untracked files, changed HEAD, changed/missing report or incomplete gate execution may authorize reuse. Unchanged short-term original PASS may be reused explicitly without new gate events. No prior proof may survive a new failing/interrupted full run. Test cases must be executed, not only documented.
