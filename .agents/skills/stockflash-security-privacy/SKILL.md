---
name: stockflash-security-privacy
description: Revisa RBAC, RUT, datos personales, secretos, sesiones, CSRF, auditoría y accesos.
compatibility: opencode
metadata:
  product: stockflash
---

# stockflash-security-privacy


Validar matriz de permisos Parte 1 §6, SEC-01..18, PRV-01..12 y restricciones Parte 2. Permisos siempre en backend; registrar lectura sensible; RUT cifrado + blind index. Evitar secretos, PII en pruebas, prompts, Engram y logs. Staging con datos sintéticos. No crear función de compliance general fuera del producto.
