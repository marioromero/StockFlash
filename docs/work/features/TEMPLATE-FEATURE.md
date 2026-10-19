# F-XXXX — nombre preciso
status: DRAFT | APPROVED | IMPLEMENTING | REVIEW | VERIFIED | RELEASED | BLOCKED
owner_approval: (fecha/enlace; obligatorio antes de implementar)

garantías: no reutilizar plantilla como Feature Packet aprobado.

## Qué / Por qué / No incluido
- Propósito concreto:
- Exclusiones:
- Entitlement / permisos si aplica:

## Referencias precisas
- Parte 1: IDs y secciones:
- Parte 2: tablas/campos:
- Anexo: secciones:
- ADR aprobado y deltas aplicados:

## Contratos/casos límite
- Invariantes:
- Datos de prueba sintéticos:

## Aceptación (EARS)
- CUANDO ..., EL SISTEMA DEBE ...
- CUANDO ..., NO DEBE ...

## Plan técnico mínimo por OpenCode
- Tareas 1–10:
- Archivos afectados previstos:
- Tests y evidencia:

## Gates
- [ ] tests
- [ ] seguridad/permisos
- [ ] responsive/táctil (si UI)
- [ ] reviewer independiente
- [ ] release de staging (si pipeline activo)

## Documentación funcional de usuario (obligatorio para funcionalidades usables)
- [ ] Guía de usuario creada en `/documentacion/modulos/<modulo>/<nombre-descriptivo>.md` con los 10 puntos obligatorios (nombre, para qué sirve, quién puede usarla, cómo acceder, cómo utilizarla, opciones, reglas y limitaciones, resultados esperados, errores frecuentes, ejemplos prácticos).
- [ ] Índice general `/documentacion/README.md` actualizado con la nueva guía.
- [ ] Enlaces internos válidos y sin duplicaciones.
- [ ] Si la funcionalidad no afecta al usuario (infraestructura o implementación interna): registrar `NO_APLICA` con justificación en el reporte del Feature Packet.

> La documentación se incluye en el mismo commit o conjunto de commits aprobados de la característica. No se considera completamente revisada una funcionalidad nueva si falta la documentación obligatoria.
