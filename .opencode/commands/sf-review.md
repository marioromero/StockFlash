---
description: Revisar diff y evidencia contra feature aprobada, sin editar
agent: stockflash-reviewer
---

Revisar Feature Packet $ARGUMENTS + git diff + resultados de pruebas, sin editar. Clasificar por severidad: correctness, permisos/privacidad, UX, performance, schema, cobertura. Si no hay código, indicar NO APLICA. Devolver APROBADO/CAMBIOS NECESARIOS y evidencia. No aprobar release sin gates ejecutados.
