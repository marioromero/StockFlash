#!/usr/bin/env python3
"""Control documental sin dependencias: ejecutar python scripts/check_contracts.py."""
from pathlib import Path
import hashlib
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
errors = []

def content(path):
    return (ROOT / path).read_text(encoding='utf-8-sig')

def require(ok, label):
    if not ok:
        errors.append(label)

p1 = content('docs/product/especificacion-v2.2-parte-1-CONSOLIDADO-EN-REVISION.md')
p2 = content('docs/product/especificacion-v2.2-parte-2-CONSOLIDADO-EN-REVISION.md')
annex = content('docs/product/especificacion-v2.2-anexo-CONSOLIDADO-EN-REVISION.md')
require('| `sale_external_tax_records` | sales | 09 |' in p2, 'Tabla externa debe figurar en lote 09')
lot09 = next((s for s in p2.splitlines() if s.startswith('| 09 | `sales` |')), '')
require('`sale_external_tax_records`' in lot09 and '`sales`, `users`(01)' in lot09, 'Orden/FK de migracion externo')
require('`sales (branch_id, sale_number)`' in p1, 'Recibo interno debe usar sales.sale_number')
require('`internal_receipt`' not in p2, 'Adaptador fiscal internal_receipt permanece en modelo')
require('sin `fiscal_documents`' in annex, 'Anexo externo no separa recibo/fiscal')
sale_section = p1.split('### 10.1 Venta directa', 1)[-1].split('### 10.2 ', 1)[0]
require('Solo con Fiscal `integrated`' in sale_section and 'en `external` no' in sale_section, 'Venta directa dispara documento fiscal indebidamente')
require('simulación exclusivamente de demo/pruebas' in p1, 'FIS-01 incompatible con nueva modalidad')
require('`sales.sale_number`' in p2, 'Modelo sin comprobante interno desde sales')
require('BOOT-001' in content('docs/work/CURRENT.md'), 'BOOT-001 no figura como activo')
require('BOOT-001 bootstrap is historical' in content('.opencode/commands/sf-feature.md') and 'No commit/push/deploy' in content('.opencode/commands/sf-feature.md'), 'Bootstrap safety or publisher boundary missing from sf-feature')
require('Staging deployment' in content('docs/harness/RELEASE-CI-CD-PLAN.md') and 'explicit owner instruction' in content('docs/harness/RELEASE-CI-CD-PLAN.md') and 'No use of old general SSH credential' in content('docs/harness/RELEASE-CI-CD-PLAN.md'), 'Owner-only staging release boundary missing')
require('grupoint' in content('docs/harness/SEGURIDAD-DE-AGENTES.md'), 'No consta riesgo de SSH general')
require('ADR-01' in content('docs/governance/DECISIONES-ABIERTAS-v2.2.md'), 'No existe el registro de decisiones pendientes')

index = content('docs/requirements/INDEX-REQUISITOS.md')
entries = re.findall(r'^\|\s*([A-Z]{2,5}-\d{2})\s*\|\s*(\d+)\s*\|', index, re.M)
p1lines = p1.splitlines()
require(len(entries) == 362, f'Indice: esperados 362 IDs, encontrados {len(entries)}')
for key, number in entries:
    n = int(number)
    require(1 <= n <= len(p1lines) and key in p1lines[n-1], f'Indice incorrecto: {key} en linea {n}')

manifest = content('docs/product/HASHES-SHA256.md')
hash_entries = re.findall(r'^- `([^`]+)` SHA256 `([a-f0-9]{64})`$', manifest, re.M)
require(len(hash_entries) == 6, f'Hashes: esperados 6, encontrados {len(hash_entries)}')
def canonical_git_bytes(path):
    # Git registra estos archivos Markdown con LF. En Windows, core.autocrlf
    # puede crear copias de trabajo CRLF sin cambiar el contenido versionado.
    # Comparamos contra la representacion canonica LF, no bytes del checkout.
    return path.read_bytes().replace(b'\r\n', b'\n')

# Regresion explicita: el mismo documento LF y CRLF debe tener igual hash.
fixture = b'linea 1\nlinea 2\n'
require(hashlib.sha256(fixture).digest() ==
        hashlib.sha256(fixture.replace(b'\n', b'\r\n').replace(b'\r\n', b'\n')).digest(),
        'Normalizacion de saltos de linea no funciona')

for relative, expected in hash_entries:
    path = ROOT / relative
    require(path.is_file() and hashlib.sha256(canonical_git_bytes(path)).hexdigest() == expected,
            f'SHA256 no coincide tras normalizar LF/CRLF: {relative}')

if errors:
    for error in errors:
        print('FAIL:', error)
    sys.exit(1)
print(f'OK DOC-001: {len(entries)} referencias, {len(hash_entries)} hashes y reglas contractuales comprobadas')
