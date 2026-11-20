---
description: Selectively commit and push an approved exact tree; never deploy or self-certify VERIFIED.
agent: stockflash-publisher
---

Delegate to `stockflash-publisher` for packet `$ARGUMENTS`. Publisher handles the publication-boundary Git sync only if necessary; do not perform a redundant command-level pull.

Operation A: packet APPROVED and active, AUTO_GIT default, matching executed GATES_PASS and independent APROBADO for exact reviewed staged tree -> explicit path staging, secret scan, safe commit, ONLY `git push origin main`, verify remote SHA, report exact-SHA GitHub CI SUCCESS/FAILURE/PENDING. No extra routine owner Git authorization.

Operation B: ChatGPT has **already** declared and documented VERIFIED with original QA/CI evidence -> publisher may commit the strictly documentary closure without rerunning functional gates **only if** there is no code/config/semantic change. Never let OpenCode independently set VERIFIED.

Inventory untracked and modified files; compare against fingerprints; exclude secrets, .env, private keys, sqlite, vendor, node_modules, public/build, unapproved DRAFT and unrelated changes. Never force/reset/stash/clean, deploy or access real remote DB. On changes after QA, STOP and require new gates/review. Preserve GPT-only HTML and context authorship. Historical HOBS audit plans authorize no extra audit-only commit. Never publish raw logs, local gate-proof files, secrets or session history.
