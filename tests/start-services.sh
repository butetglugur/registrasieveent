#!/usr/bin/env bash
# Menyalakan layanan uji lokal: MariaDB, PHP built-in server (:8080), mock WA API (:8099), mock SMTP (:2525), Apache (bila ada).
cd "$(dirname "$0")/.."
mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld 2>/dev/null
mysqladmin ping >/dev/null 2>&1 || (mysqld_safe --user=mysql > /dev/null 2>&1 &)
for i in $(seq 1 30); do mysqladmin ping >/dev/null 2>&1 && break; sleep 1; done
up() { php -r 'exit(@fsockopen("127.0.0.1", (int) $argv[1]) ? 0 : 1);' "$1"; }
up 8080 || (php -S 127.0.0.1:8080 -t presensi/public tests/server-router.php > /dev/null 2>&1 &)
up 8099 || (php -S 127.0.0.1:8099 tests/mock-api.php > /dev/null 2>&1 &)
up 2525 || (php tests/mock-smtp.php 2525 > /dev/null 2>&1 &)
command -v apache2ctl >/dev/null && (up 8090 || apache2ctl start 2>/dev/null)
sleep 1
mysqladmin ping
