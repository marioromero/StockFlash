# StockFlash — Contrato RUN-REPORT v1 (propuesto, sin implementar)

**Estado de este documento: DOCUMENTADO / NO OPERATIVO.** Copiar esta plantilla no genera ejecución, evidencia ni automatización. La incorporación a OpenCode requiere packet técnico propio, scripts deterministas y test E2E (DV-007). No reemplaza los artefactos `docs/work/*-VERIFY-*.md` y `*-REVIEW-*.md` ya existentes.

## Finalidad
Una respuesta verificable y compacta por ejecución: evita pegar 2.000 líneas de logs, facilita reanudar otro chat y permite un informe de proyecto real. La fuente es Git y las herramientas; los agentes **solo narran lo que quedó probado**.

## Formato propuesto
```text
=== RUN-REPORT v1 ===
run_id: <ID+intento>
feature: <ID>
result: DONE|STOP|BLOCKED|FAILED
scope: <paths/packet aprobado>
gates: PASS|FAIL|UNVERIFIED (report_path)
qa: APPROVED|CHANGES_REQUIRED|UNVERIFIED (review_path)
fingerprint: <hash de los archivos verificados o UNKNOWN>
git: local=<SHA|UNKNOWN> remote=<SHA|UNKNOWN> pushed=yes|no|UNKNOWN
ci: SUCCESS|FAILURE|PENDING|CI_UNVERIFIED (run URL + SHA)
agents: orchestrator=<ran|manual|unknown> implementer=<ran|unknown> reviewer=<ran|unknown> publisher=<ran|unknown>
tests: passed=<n|UNKNOWN> failed=<n|UNKNOWN>
decisions: [IDs|none] / blocked_by=<ID|none>
next: <una acción exacta>
=== END ===
```

## Reglas para implementarlo después
1. Generar `run_id` estable y guardar historial append-only (no sobrescribir reportes previos) con timestamps UTC.
2. Extraer hechos con comandos/herramientas: `git status`, `git rev-parse`, `git ls-remote`, salida estructurada de `sf_verify.py`, reporte atribuible del reviewer y GitHub run ID/SHA final. No inferir CI por un verde antiguo.
3. Incluir archivos nuevos/untracked en el inventario; calcular fingerprints **antes** de revisión y compararlos **antes** de publicar. Un cambio invalida evidencia relevante.
4. El estado `DONE` significa implementación publicada y QA/gates válidos; `VERIFIED` sigue siendo **decisión externa** y no se concede por el reporte.
5. Mostrar `UNKNOWN/CI_UNVERIFIED` cuando no haya prueba. No pedir tokens o coste si el proveedor no expone esa métrica.
6. Registrar decisiones no bloqueantes y fallos de delegación sin maquillar una coordinación manual como automática.
7. Probar parser, formato, idempotencia del ledger y reanudación con un packet administrativo independiente antes de habilitar en producción del harness.

## Evidencia actual válida
- CI-001: GitHub Actions CI `38655297831` success + contratos `38655297947` success sobre `218de3ad2a51be6f7b46102b8a9b73569d53cf0b`.
- Seguimiento operativo vigente: `docs/work/CURRENT.md`. No existe todavía ningún `RUN-REPORT` generado por máquina.
