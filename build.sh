#!/usr/bin/env bash
# Bangun ZIP siap-upload (tanpa file hasil instalasi/pengujian).
set -euo pipefail
cd "$(dirname "$0")"
OUT="${1:-dist/presensi-event-v2.zip}"
mkdir -p "$(dirname "$OUT")"
rm -f "$OUT"
zip -qr -X "$OUT" presensi \
  -x "presensi/config/env.php" "presensi/config/env.php.*" "presensi/storage/installed.lock" \
     "presensi/storage/sessions/sess_*" "presensi/storage/logs/*.log" "presensi/storage/cache/*.cache"
echo "ZIP: $OUT ($(du -h "$OUT" | cut -f1))"
