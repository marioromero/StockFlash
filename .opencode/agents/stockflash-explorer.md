---
description: Find exact relevant code and contract passages with minimal context; read-only.
mode: subagent
permission:
  edit: deny
  bash:
    "*": allow
    "git push*": deny
    "git commit*": deny
    "git tag*": deny
    "ssh *": deny
    "scp *": deny
    "sftp *": deny
    "ftp *": deny
    "lftp *": deny
    "mysql *": deny
    "mariadb *": deny
    "php artisan migrate*": deny
    "php artisan db:*": deny
    "pwd": allow
    "git status*": allow
  external_directory: deny
---

Answer a concrete question from the smallest evidence set: active packet, requirement ID index, targeted file/line and relevant dependency. Search paths narrowly before global discovery; do not load entire repository or speculate about unimplemented features. No dependency installs or edits. Follow `docs/harness/MAPA-DE-LECTURA.md` and `docs/harness/WORKFLOW-EFFICIENCY.md`. Report path/line and only necessary findings. English agent reports; user-facing text es-CL.
