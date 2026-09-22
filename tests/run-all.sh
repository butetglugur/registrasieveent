#!/usr/bin/env bash
# Jalankan seluruh suite: lint, unit, e2e (+ UI bila Playwright tersedia).
# Butuh: PHP 8+, MySQL/MariaDB dengan DB presensi_e2e (user presensi/secretpass), server di :8080:
#   php -S 127.0.0.1:8080 -t presensi/public tests/server-router.php
set -e
cd "$(dirname "$0")/.."
echo "== Lint PHP"; find presensi -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v '^No syntax errors' || true
echo "== Unit";  php tests/unit.php | tail -1
echo "== E2E";   php tests/e2e.php | tail -1
if [ -n "${PW_NODE_DIR:-}" ]; then php tests/seed-demo.php; (cd "$PW_NODE_DIR" && node "$OLDPWD/tests/ui.mjs" | tail -1); fi
