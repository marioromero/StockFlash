# INVTRANSFER-001 — Independent QA re-review (i=003 correction) — 2026-11-20 15:15 CLT

## Verdict
**APROBADO** — 0 blocking findings. Independent read-only QA; **not VERIFIED**.

- Packet: `docs/work/features/INVTRANSFER-001.md` (`status: APPROVED`, `AUTO_GIT`, `ONE_SMALL_WAVE`, `new_business_decisions: NONE`).
- Base = HEAD = `origin/main`: `d2b051307a471bfd9bce4ce90d79970fd0325a18` (includes published `ccf64fb` and unrelated HQAEFF-002 merge).
- Re-review scope: external items **A** and **B** only; prior N1/N2 closure first, then regression radius. No QA edit, commit, push, deploy or remote DB.

## Fingerprint (exactly the 7 candidate paths)
```text
26a4a2261c2b316475f685f210da9e9867485fff  app/Support/Inventory/StockTransferQueryService.php
99ac203c2498851a5cffac95778e3643439227ba  app/Support/Inventory/StockTransferService.php
7324d31a9dfb934cea0b66b51ce0d3ff39aed772  docs/work/INVTRANSFER-001-NOTES-20261120.md
bda8a2679d322dc5ca27ffeff9a12ca3aedc7131  scripts/sf_verify.py
90445ddcca52d72884350862d7754d13a6acd5d9  tests/Feature/Inventory/StockTransferLifecycleTest.php
4c348d9595d975b6c0a50e69de6b798b1292bfba  tests/Feature/Inventory/StockTransferQueryTest.php
a8c9ae8b8e264fa0b2b0681a51fb192633d7e1ef  docs/work/INVTRANSFER-001-VERIFY-20261120-151141.md
```
`git status --porcelain=v1 --untracked-files=all` shows exactly these 6 modifications + 1 untracked report; no stray edits. `docs/work/.run/` is gitignored proof, not source.

## Gate evidence (trusted, exact tree)
- Report `docs/work/INVTRANSFER-001-VERIFY-20261120-151141.md` = **GATES_PASS 18/18** (IT1–IT17 + IT7b, all mandatory); inventory **217 passed / 2999 assertions**, full suite **819 / 8610**, contracts, typecheck, build PASS.
- Recomputed fingerprint now: `dd1b5b42…2222`, 725 files, HEAD `d2b0513` — **exact match** with `docs/work/.run/INVTRANSFER-001.gate-evidence.json`; report SHA-256 `0027ed2a…f901` matches the proof. Tests run by QA did not mutate the tree (fingerprint unchanged).
- Gate plan diff is limited to assertions for the new invariants in IT6 (`prefixOverride`, `str_pad`), IT7b (`fallo_de_auditoria_en_solicitud`, `codigos_de_origen_mayores`, `draft_muestra_numero_canonico`) and IT14 (`preg_match`); no gate removed or made optional.

## A) Long branch codes > 10 — CLOSED
- `request()` reads the origin code inside the locked transaction and passes `prefixOverride` **only** when `strlen(code) > 10`; otherwise it keeps the historical `allocate('transfer', origin)` (`StockTransferService.php:216-230`).
- `transferSequencePrefix` = `'T' . last 9 of zero-padded branch id` → always exactly 10 chars, deterministic/stable from scope id (`StockTransferService.php:797-800`).
- Allocator contract honored: override is used only on `insertOrIgnore`; existing rows keep their `prefix/padding/next_number` (`DocumentSequenceAllocator.php:72-96`); max prefix 10, padding 6..18. `branches.code` remains `VARCHAR(30)` + immutable (`BranchRequest.php:61-62,97-101`); no schema change.
- Tests: 11- and 30-char origins request/dispatch with 16-char unique origin-scoped numbers, second transfer `000002`, zero stock/movements in draft/request, stock moves only on dispatch (`StockTransferLifecycleTest.php:232-278`); request audit failure keeps `draft`, no sequence row/consumption, no audit/stock, retry yields `000001` (`:280-336`).
- Targeted probe: `StockTransferLifecycleTest` filter → **2 passed / 51 assertions**; allocator contract → **21 passed / 94 assertions**. Short-code behavior unchanged (e.g. `ORI-RAUD000001`).

## B) `DRAFT-` display — CLOSED
- Both implementations now hide only `status === 'draft'` or the exact internal placeholder `^DRAFT-[0-9A-F]{24}$` (`StockTransferQueryService.php:299-306`; `StockTransferService.php:809-818`).
- Canonical max length is 28 (prefix ≤10 + padding ≤18) vs 30-char uppercase marker, so no legitimate canonical number can be hidden; marker is generated uppercase (`StockTransferService.php:788`).
- List and detail both route through `publicNumber` (`QueryService.php:99,220`); UI keeps the `?? 'Borrador'` fallback (`Transfers.vue:226`, `TransferShow.vue:22`).
- Tests: canonical `DRAFT-000001` visible in detail **and** list at `requested`, `in_transit`, `partially_received` (`StockTransferQueryTest.php:72-158`) and `received` (`:160-218`); cancelled draft placeholder stays hidden in detail/list (`:201-218`).
- Targeted probe: `StockTransferQueryTest` → **6 passed / 298 assertions**.

## Regression / scope
- Lifecycle + access regression: **29 passed / 442 assertions** (SQLite `:memory:`).
- Protected surfaces untouched: `AGENTS.md`, `opencode.json`, constitution, `docs/product`, `docs/branding`, `docs/harness`, `public/docs/index.html`, `resources/roadmap/index.html`, packet file, `database/` all unchanged; `git diff --check HEAD` clean (CRLF warnings only). No secrets, `.env`, remote DB, migration/seeder, deploy or commit/push.

## Prior-finding dispositions
- **N1 (legitimate number hidden) — RESOLVED** by B.
- **N2 (long-code allocation dead-end) — RESOLVED** by A.
- **N4 (request audit-rollback test gap) — RESOLVED** by the new request audit-failure test.
- N3 (cost snapshot deferral), N5 (GUIA/ROADMAP stale wording), N6 (pre-existing `CheckPermission` token handling) unchanged, non-blocking, not reopened.

## Minor observation (non-blocking)
- **N7 — documentation accuracy.** The new NOTES disposition bullet says “Sin cambios de esquema, contratos, harness ni `sf_verify`”, and the older bullet still says `scripts/sf_verify.py` was not executed; both are contradicted by the modified `sf_verify.py` (hash `bda8a26`) and the 151141 gate run. Documentation-only; recommend correcting before publication/VERIFIED. Does not affect A/B or code acceptance.

## Not run / deferrals
- QA did **not** replay the full suite, inventory suite, contracts, typecheck or build; relied on the exact-fingerprint GATES_PASS proof plus targeted probes.
- MariaDB real concurrency/deadlocks/indices, browser UX, remote DB, migrations/seeds, deploy, commit/push: **not run**.
- `GUIA_PENDIENTE_CHATGPT` / `ROADMAP_PENDIENTE_CHATGPT` remain ChatGPT-exclusive; CI at a published SHA remains **PENDING**.

## Status
Independent read-only QA **APROBADO** for the exact fingerprint above. This is **not** `VERIFIED`; only external ChatGPT review of approved acceptance + independent QA + gates + exact-SHA CI may record technical `VERIFIED`, with MariaDB/browser/production checks explicitly deferred. `AUTO_GIT` may proceed only for this exact tree and does not authorize deploy, remote DB operations, migrations/seeding or production.
