# Presensi Event v2

Aplikasi registrasi & presensi event berbasis **PHP native** dengan struktur ala Laravel
(tanpa Composer, tanpa SSH) — siap di-upload ke DirectAdmin / cPanel.

- Kode aplikasi: [`presensi/`](presensi/) — panduan lengkap: [`presensi/PANDUAN-INSTALL.txt`](presensi/PANDUAN-INSTALL.txt)
- Build ZIP siap-upload: `./build.sh` → `dist/presensi-event-v2.zip`
- Test: `tests/run-all.sh` (lint + unit + end-to-end, opsional UI Playwright)

## Struktur

```
presensi/
├── app/            Controllers, Models, Middleware, Core (router, view, DB, validator, auth…)
├── bootstrap/      autoload & bootstrap
├── config/         app.php, database.php (+ env.php dibuat installer)
├── database/       schema.sql
├── public/         front controller + aset (CSS/JS/font/QR lib lokal)
├── resources/views layout & halaman (PHP template ala Blade)
├── routes/web.php  definisi route
└── storage/        log & session (diblokir dari web)
```
