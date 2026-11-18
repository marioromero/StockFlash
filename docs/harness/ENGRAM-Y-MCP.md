# Engram, MCP and CLI tools — conservative optional integrations

Engram is optional local context memory, not policy. Never store secrets, RUT/PII, customer data, credentials or full transcripts. Save only short reproducible learnings with a reference to feature/commit/ADR if useful.

Install **no new MCP or global tool integration** without reviewing exact installed client version, permissions, token overhead, exit/disable plan and user-approved security exposure. The system may support Playwright smoke tests, DevTools, Context7, MariaDB read-only MCP and provider models, but support is not evidence that it is already configured. Use native CLI/file operations by default.

Any MariaDB MCP must use a separate read-only test account and synthetic data. No production database or general-purpose staging accounts. `engram setup opencode` may change user-global settings, so do not run automatically. Measure A/B benefit with equivalent tasks before enabling a tool that expands context.

For current efficiency and token audit use MAPA-DE-LECTURA.md, WORKFLOW-EFFICIENCY.md and AUDITORIA-TOKENS-IA.md; external AI analysis prompts stay outside Git.
