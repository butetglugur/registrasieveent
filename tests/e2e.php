<?php
/**
 * End-to-end test melalui HTTP sungguhan + MySQL/MariaDB sungguhan.
 *
 * Prasyarat: server berjalan di E2E_URL (default http://127.0.0.1:8080)
 * dengan document root presensi/public, dan database kosong E2E_DB.
 * Jalankan: php tests/e2e.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$BASE = getenv('E2E_URL') ?: 'http://127.0.0.1:8080';
$PREFIX = rtrim((string) parse_url($BASE, PHP_URL_PATH), '/');   // mis. /presensi bila di subfolder
$DB = ['host' => getenv('E2E_DB_HOST') ?: '127.0.0.1', 'name' => getenv('E2E_DB') ?: 'presensi_e2e',
       'user' => getenv('E2E_DB_USER') ?: 'presensi', 'pass' => getenv('E2E_DB_PASS') ?: 'secretpass'];
$APP = getenv('E2E_APP') ?: realpath(__DIR__ . '/../presensi');

$p = static fn(string $path) => $PREFIX . $path;      // path lengkap (untuk cek Location)

// ---------- Reset lingkungan ----------
@unlink($APP . '/config/env.php');
@unlink($APP . '/storage/installed.lock');
array_map('unlink', glob($APP . '/storage/sessions/sess_*') ?: []);
$pdo = new PDO("mysql:host={$DB['host']};dbname={$DB['name']};charset=utf8mb4", $DB['user'], $DB['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $pdo->exec("DROP TABLE `{$t}`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
// Tabel versi lama (untuk uji impor)
$pdo->exec("CREATE TABLE attendees (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), address VARCHAR(255), representative VARCHAR(100), wa VARCHAR(30), extra TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE settings (id INT PRIMARY KEY, group_link VARCHAR(255), form_fields TEXT)");
$pdo->exec("CREATE TABLE admins (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50), password_hash VARCHAR(255))");
$pdo->exec("INSERT INTO settings VALUES (1, 'https://chat.whatsapp.com/LAMA123', '[{\"key\":\"jabatan\",\"label\":\"Jabatan\",\"required\":1}]')");
$st = $pdo->prepare('INSERT INTO attendees (name,address,representative,wa,extra,created_at) VALUES (?,?,?,?,?,?)');
$st->execute(['Peserta Lama 1', 'Medan', 'PT Lama', '0812-1111-2222', '{"jabatan":"Manager"}', '2025-01-10 10:00:00']);
$st->execute(['Peserta Lama 2', '', '', '+62 813 3333 4444', '[]', '2025-01-11 11:00:00']);
$pdo->prepare('INSERT INTO admins (username,password_hash) VALUES (?,?)')->execute(['adminlama', password_hash('rahasiaLama1', PASSWORD_DEFAULT)]);

$web = new Http($BASE);
$admin = new Http($BASE);

// =====================================================================
T::group('Sebelum instalasi');
$web->get('/');
T::eq(302, $web->status, 'halaman depan redirect ke installer');
T::ok(str_ends_with($web->location(), '/install'), 'Location = /install', $web->location());
$web->get('/admin/login');
T::eq(302, $web->status, 'admin juga redirect ke installer');
$web->get('/install');
T::eq(200, $web->status, 'installer tampil');
T::contains('Pemeriksaan server', $web->body, 'daftar pemeriksaan server tampil');
T::ok($web->header('content-security-policy') !== '', 'header CSP dikirim');
T::eq('SAMEORIGIN', $web->header('x-frame-options'), 'header X-Frame-Options');
T::eq('nosniff', $web->header('x-content-type-options'), 'header nosniff');
$csrf = $web->csrf();
T::ok(strlen($csrf) === 64, 'token CSRF ada di form');

$installData = [
    '_token' => $csrf, 'db_host' => $DB['host'], 'db_port' => '3306', 'db_name' => $DB['name'], 'db_user' => $DB['user'],
    'db_pass' => $DB['pass'], 'app_name' => 'Presensi DMI', 'app_url' => '', 'admin_name' => 'Super Admin',
    'admin_username' => 'admin', 'admin_password' => 'Rahasia123', 'admin_password_confirmation' => 'Rahasia123',
    'import_legacy' => '1',
];
$web->post('/install', ['_token' => 'salah'] + $installData);
T::eq(419, $web->status, 'POST tanpa CSRF valid ditolak (419)');

$web->post('/install', ['db_pass' => 'passwordsalah'] + $installData);
T::eq(302, $web->status, 'password DB salah -> kembali ke form');
$web->follow();
T::contains('Gagal terhubung ke database', $web->body, 'pesan koneksi DB gagal tampil');
T::notContains('passwordsalah', $web->body, 'password DB tidak bocor di halaman');
T::notContains('secretpass', $web->body, 'password DB asli tidak bocor');

$web->post('/install', ['admin_password' => 'lemah', 'admin_password_confirmation' => 'lemah', '_token' => $web->csrf()] + $installData);
$web->follow();
T::contains('Password admin minimal 8 karakter', $web->body, 'password admin lemah ditolak');
T::ok(!is_file($APP . '/config/env.php'), 'env.php belum dibuat saat validasi gagal');

$web->post('/install', ['_token' => $web->csrf()] + $installData);
T::eq(302, $web->status, 'instalasi sukses -> redirect');
T::ok(str_ends_with($web->location(), '/admin/login'), 'redirect ke login', $web->location());
T::ok(is_file($APP . '/config/env.php'), 'config/env.php dibuat');
T::ok(is_file($APP . '/storage/installed.lock'), 'installed.lock dibuat');
$env = require $APP . '/config/env.php';
T::eq(64, strlen((string) $env['APP_KEY']), 'APP_KEY acak 256-bit');
T::eq(false, $env['APP_DEBUG'], 'debug mati di produksi');
$web->follow();
T::contains('Data lama berhasil diimpor: 2 peserta, 1 admin', $web->body, 'impor data lama dilaporkan');

$web->get('/install');
T::eq(404, $web->status, 'installer terkunci setelah instalasi');
$web->post('/install', ['_token' => $web->csrf()] + $installData);
T::ok(in_array($web->status, [404, 419], true), 'POST installer juga ditolak setelah instalasi');

// =====================================================================
T::group('Impor data lama');
$legacyEvent = $pdo->query("SELECT * FROM events WHERE slug = 'seminar'")->fetch(PDO::FETCH_ASSOC);
T::ok((bool) $legacyEvent, 'event hasil impor dibuat');
T::eq('https://chat.whatsapp.com/LAMA123', $legacyEvent['group_link'] ?? '', 'link grup lama dibawa');
T::contains('"label":"Jabatan"', (string) ($legacyEvent['fields'] ?? ''), 'field dinamis lama dibawa');
$regs = $pdo->query('SELECT * FROM registrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
T::eq(2, count($regs), '2 peserta diimpor');
T::eq('6281211112222', $regs[0]['wa'] ?? '', 'nomor WA dinormalisasi saat impor');
T::eq('2025-01-10 10:00:00', $regs[0]['created_at'] ?? '', 'waktu daftar asli dipertahankan');
T::eq(1, (int) $pdo->query("SELECT COUNT(*) FROM attendees")->fetchColumn() >= 1 ? 1 : 0, 'tabel lama tidak dihapus');

// =====================================================================
T::group('Halaman publik');
$web->get('/');
T::eq(200, $web->status, 'beranda tampil');
T::contains('Seminar Digital Media Inspirasi', $web->body, 'event terbuka tampil di beranda');
T::notContains('Seminar Contoh', $web->body, 'event contoh tidak dibuat karena sudah ada event');
$web->get('/index.php');
T::eq(200, $web->status, 'URL lama /index.php tetap berfungsi');
$web->get('/tidak-ada-halaman');
T::eq(404, $web->status, 'halaman tak dikenal -> 404');
T::contains('Halaman tidak ditemukan', $web->body, 'halaman 404 yang ramah');
$web->get('/e/seminar');
T::eq(200, $web->status, 'halaman event tampil');
T::contains('name="website"', $web->body, 'honeypot anti-bot ada');
T::contains('Jabatan', $web->body, 'field dinamis tampil');
$web->request('PUT', '/e/seminar');
T::eq(405, $web->status, 'metode salah -> 405');
$web->get('/assets/css/app.css');
T::eq(200, $web->status, 'aset CSS dapat diakses');

// =====================================================================
T::group('Login admin');
$admin->get('/admin');
T::eq(302, $admin->status, 'admin tanpa login redirect');
T::ok(str_ends_with($admin->location(), '/admin/login'), 'ke halaman login');
$admin->get('/admin/login');
T::eq(200, $admin->status, 'halaman login tampil');
$admin->post('/admin/login', ['_token' => $admin->csrf(), 'username' => 'admin', 'password' => 'salah123']);
$admin->follow();
T::contains('Username atau password salah', $admin->body, 'password salah ditolak');

$brute = new Http($BASE);
$brute->get('/admin/login');
for ($i = 0; $i < 5; $i++) {
    $brute->post('/admin/login', ['_token' => $brute->csrf(), 'username' => 'korban', 'password' => 'tebak' . $i]);
    $brute->follow();
}
$brute->post('/admin/login', ['_token' => $brute->csrf(), 'username' => 'korban', 'password' => 'tebak-lagi']);
$brute->follow();
T::contains('Terlalu banyak percobaan login', $brute->body, 'brute-force diblokir setelah 5 percobaan');

$legacy = new Http($BASE);
$legacy->get('/admin/login');
$legacy->post('/admin/login', ['_token' => $legacy->csrf(), 'username' => 'adminlama', 'password' => 'rahasiaLama1']);
T::ok(str_ends_with($legacy->location(), '/admin'), 'admin lama bisa login dengan password lama', $legacy->location());

$sidBefore = $admin->cookies['presensi_session'] ?? '';
$admin->post('/admin/login', ['_token' => $admin->csrf(), 'username' => 'admin', 'password' => 'Rahasia123']);
T::eq(302, $admin->status, 'login sukses');
T::ok(($admin->cookies['presensi_session'] ?? '') !== $sidBefore, 'session ID diganti setelah login (anti session fixation)');
$admin->follow();
T::eq(200, $admin->status, 'dasbor tampil');
T::contains('Selamat datang, Super Admin', $admin->body, 'pesan selamat datang');
T::contains('Total pendaftar', $admin->body, 'kartu statistik tampil');
T::eq('no-store, private', $admin->header('cache-control'), 'halaman admin tidak di-cache');
$admin->get('/admin/login');
T::eq(302, $admin->status, 'halaman login redirect bila sudah login');

// =====================================================================
T::group('Kelola event');
$admin->get('/admin/event/baru');
T::eq(200, $admin->status, 'form event baru tampil');
$tok = $admin->csrf();
$fields = json_encode([
    ['label' => 'Jabatan', 'type' => 'text', 'required' => true],
    ['label' => 'Ukuran Kaos', 'type' => 'select', 'required' => true, 'options' => ['S', 'M', 'L']],
    ['label' => 'Minat', 'type' => 'checkbox', 'options' => ['Web', 'Mobile', 'AI']],
]);
$eventData = [
    '_token' => $tok, 'title' => 'Workshop AI 2026', 'slug' => '', 'subtitle' => 'Belajar AI praktis',
    'description' => "Baris 1\nBaris 2", 'location' => 'Hotel Santika Medan', 'starts_at' => date('Y-m-d', strtotime('+10 days')) . 'T09:00',
    'ends_at' => date('Y-m-d', strtotime('+10 days')) . 'T12:00', 'status' => 'open', 'quota' => '4', 'closes_at' => '',
    'group_link' => 'javascript:alert(1)', 'success_message' => 'Sampai jumpa!', 'redirect_seconds' => '0', 'theme' => 'ocean',
    'representative_label' => 'Asal Kampus', 'fields' => $fields, 'dedupe_wa' => '1', 'show_email' => '1', 'require_email' => '1',
    'show_representative' => '1', 'show_address' => '1',
];
$admin->post('/admin/event', $eventData);
$admin->follow();
T::contains('Link grup WhatsApp harus berupa URL yang valid', $admin->body, 'link javascript: ditolak');
T::contains('value="Workshop AI 2026"', $admin->body, 'input lama dipertahankan setelah gagal validasi');

$admin->post('/admin/event', ['_token' => $admin->csrf(), 'group_link' => 'https://chat.whatsapp.com/GRUPAI'] + $eventData);
T::eq(302, $admin->status, 'event dibuat');
$editUrl = $admin->location();
T::ok((bool) preg_match('#/admin/event/(\d+)/edit$#', $editUrl, $m), 'redirect ke halaman edit', $editUrl);
$eventId = (int) ($m[1] ?? 0);
$admin->follow();
T::contains('Event berhasil dibuat', $admin->body, 'flash sukses');
T::contains('/e/workshop-ai-2026', $admin->body, 'slug otomatis dari judul');

// Slug bentrok
$admin->post('/admin/event', ['_token' => $admin->csrf(), 'slug' => 'workshop-ai-2026', 'group_link' => ''] + $eventData);
$admin->follow();
T::contains('sudah dipakai event lain', $admin->body, 'slug duplikat ditolak');
$admin->post('/admin/event', ['_token' => $admin->csrf(), 'ends_at' => date('Y-m-d') . 'T01:00', 'group_link' => ''] + $eventData);
$admin->follow();
T::contains('Waktu selesai harus setelah waktu mulai', $admin->body, 'waktu selesai < mulai ditolak');

$admin->get('/admin/event');
T::eq(200, $admin->status, 'daftar event tampil');
T::contains('Workshop AI 2026', $admin->body, 'event baru ada di daftar');

// =====================================================================
T::group('Pendaftaran publik');
$guest = new Http($BASE);
$guest->get('/e/workshop-ai-2026');
T::eq(200, $guest->status, 'form pendaftaran tampil');
T::contains('Asal Kampus', $guest->body, 'label perwakilan kustom');
T::contains('Ukuran Kaos', $guest->body, 'field select tampil');
T::contains('--g1:#0ea5e9', $guest->body, 'tema ocean diterapkan');
$ts = $guest->field('ts');
$reg = [
    '_token' => $guest->csrf(), 'ts' => $ts, 'website' => '', 'name' => 'Budi Santoso', 'wa' => '0812-3456-7890',
    'email' => 'budi@example.com', 'address' => 'Jl. Merdeka 1', 'representative' => 'USU',
    'extra' => ['jabatan' => 'Mahasiswa', 'ukuran_kaos' => 'M', 'minat' => ['Web', 'AI']],
];
$guest->post('/e/workshop-ai-2026', $reg);
$guest->follow();
T::contains('Pengiriman ditolak', $guest->body, 'kirim terlalu cepat (<2 detik) ditolak (anti-bot)');
sleep(2);
$guest->get('/e/workshop-ai-2026');
$reg['_token'] = $guest->csrf();
$guest->post('/e/workshop-ai-2026', ['website' => 'http://spam'] + $reg);
$guest->follow();
T::contains('Pengiriman ditolak', $guest->body, 'honeypot terisi ditolak');

$guest->get('/e/workshop-ai-2026');
$reg['_token'] = $guest->csrf();
$guest->post('/e/workshop-ai-2026', ['extra' => ['jabatan' => 'X', 'ukuran_kaos' => 'XXL']] + $reg);
$guest->follow();
T::contains('Pilihan Ukuran Kaos tidak valid', $guest->body, 'opsi select di luar daftar ditolak');
T::contains('value="Budi Santoso"', $guest->body, 'input lama dipertahankan');

$guest->post('/e/workshop-ai-2026', ['wa' => '123', '_token' => $guest->csrf()] + $reg);
$guest->follow();
T::contains('Nomor WhatsApp tidak valid', $guest->body, 'nomor WA tidak valid ditolak');
$guest->post('/e/workshop-ai-2026', ['email' => '', '_token' => $guest->csrf()] + $reg);
$guest->follow();
T::contains('Email wajib diisi', $guest->body, 'email wajib (sesuai pengaturan event)');

$guest->post('/e/workshop-ai-2026', ['_token' => $guest->csrf()] + $reg);
T::eq(302, $guest->status, 'pendaftaran valid diterima');
T::ok((bool) preg_match('#/t/([A-Z0-9]{8})\?baru=1$#', $guest->location(), $m), 'redirect ke tiket', $guest->location());
$code = $m[1] ?? '';
$guest->follow();
T::eq(200, $guest->status, 'halaman tiket tampil');
T::contains('Pendaftaran berhasil', $guest->body, 'pesan sukses');
T::contains('Sampai jumpa!', $guest->body, 'pesan sukses kustom');
T::contains('https://chat.whatsapp.com/GRUPAI', $guest->body, 'tombol gabung grup WA');
T::contains('data-qr="', $guest->body, 'QR tiket dirender');
T::contains($code, $guest->body, 'kode tiket tampil');
$row = $pdo->query("SELECT * FROM registrations WHERE code = " . $pdo->quote($code))->fetch(PDO::FETCH_ASSOC);
T::eq('6281234567890', $row['wa'] ?? '', 'WA tersimpan ternormalisasi');
T::eq('{"jabatan":"Mahasiswa","ukuran_kaos":"M","minat":["Web","AI"]}', $row['extra'] ?? '', 'data tambahan tersimpan sebagai JSON');

// Duplikat
$guest->get('/e/workshop-ai-2026');
$guest->post('/e/workshop-ai-2026', ['_token' => $guest->csrf(), 'wa' => '+62 812 3456 7890', 'name' => 'Budi Lagi'] + $reg);
T::ok(str_ends_with($guest->location(), '/e/workshop-ai-2026/terdaftar'), 'nomor WA sama terdeteksi duplikat', $guest->location());
$guest->follow();
T::contains('Nomor ini sudah terdaftar', $guest->body, 'halaman sudah terdaftar');
T::contains('https://chat.whatsapp.com/GRUPAI', $guest->body, 'link grup tetap diberikan');
$cnt = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE event_id = {$eventId}")->fetchColumn();
T::eq(1, $cnt, 'tidak ada data ganda');

// XSS
$guest->get('/e/workshop-ai-2026');
$guest->post('/e/workshop-ai-2026', ['_token' => $guest->csrf(), 'wa' => '081311112222', 'name' => '<script>alert("xss")</script>', 'representative' => '=HYPERLINK("http://evil")'] + $reg);
$xssTicket = $guest->location();
$guest->follow();
T::notContains('<script>alert("xss")</script>', $guest->body, 'nama berisi <script> di-escape di tiket');
T::contains('&lt;script&gt;', $guest->body, 'nama tampil sebagai teks');

// Kuota: sisa 2 slot (kuota 4)
for ($i = 0; $i < 2; $i++) {
    $g = new Http($BASE);
    $g->get('/e/workshop-ai-2026');
    $tsx = $g->field('ts');
    $data[$i] = ['_token' => $g->csrf(), 'ts' => $tsx, 'name' => 'Peserta ' . $i, 'wa' => '08520000000' . $i] + $reg;
    $gs[$i] = $g;
}
sleep(2);
foreach ($gs as $i => $g) {
    $g->post('/e/workshop-ai-2026', $data[$i]);
    T::ok(str_contains($g->location(), '/t/'), 'peserta kuota #' . ($i + 1) . ' diterima', $g->location());
}
$g = new Http($BASE);
$g->get('/e/workshop-ai-2026');
T::contains('Kuota peserta sudah penuh', $g->body, 'form ditutup saat kuota penuh');
T::notContains('name="website"', $g->body, 'formulir tidak ditampilkan saat penuh');
$g->post('/e/workshop-ai-2026', ['_token' => $g->csrf(), 'wa' => '0899999999'] + $reg);
$g->follow();
T::contains('Kuota peserta sudah penuh', $g->body, 'POST langsung juga ditolak saat penuh');
T::eq(4, (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE event_id = {$eventId}")->fetchColumn(), 'jumlah peserta tidak melebihi kuota');

// ICS
$guest->get('/e/workshop-ai-2026/kalender.ics');
T::eq(200, $guest->status, 'file kalender .ics');
T::contains('BEGIN:VEVENT', $guest->body, 'isi VEVENT');
T::contains('text/calendar', $guest->header('content-type'), 'content-type kalender');

// =====================================================================
T::group('Data peserta (admin)');
$admin->get('/admin/peserta?event=' . $eventId);
T::eq(200, $admin->status, 'daftar peserta tampil');
T::contains('Budi Santoso', $admin->body, 'peserta tampil');
T::notContains('<script>alert("xss")</script>', $admin->body, 'XSS di-escape di panel admin');
$admin->get('/admin/peserta?q=santoso');
T::contains('Budi Santoso', $admin->body, 'pencarian nama');
T::notContains('Peserta 0', $admin->body, 'pencarian menyaring hasil');
$admin->get('/admin/peserta?q=3456');
T::contains('Budi Santoso', $admin->body, 'pencarian nomor WA');
$admin->get('/admin/peserta?q=' . urlencode("%' OR 1=1 -- "));
T::eq(200, $admin->status, 'input SQL injection di pencarian aman');
T::contains('Tidak ada hasil', $admin->body, 'wildcard % di-escape');
$budiId = (int) $row['id'];
$admin->get('/admin/peserta/' . $budiId);
T::eq(200, $admin->status, 'detail peserta tampil');
T::contains('Mahasiswa', $admin->body, 'field tambahan tampil di detail');
T::contains('Web, AI', $admin->body, 'jawaban checkbox tampil');

// =====================================================================
T::group('Check-in');
$admin->get('/admin/checkin?event=' . $eventId);
T::eq(200, $admin->status, 'halaman check-in tampil');
$tok = $admin->csrf();
$jh = ['Accept: application/json', 'X-Requested-With: XMLHttpRequest'];
$admin->post('/admin/checkin', ['_token' => $tok, 'code' => $BASE . '/t/' . $code, 'event_id' => $eventId], $jh);
$j = $admin->json();
T::eq(200, $admin->status, 'check-in via URL QR sukses');
T::eq(true, $j['ok'] ?? null, 'respon ok=true');
T::eq('Budi Santoso', $j['registration']['name'] ?? '', 'nama peserta dikembalikan');
T::eq(1, $j['stats']['checked_in'] ?? 0, 'statistik hadir diperbarui');
$admin->post('/admin/checkin', ['_token' => $tok, 'code' => strtolower($code), 'event_id' => $eventId], $jh);
T::eq(409, $admin->status, 'scan kedua -> sudah check-in (409)');
T::eq('already', $admin->json()['status'] ?? '', 'status already');
$admin->post('/admin/checkin', ['_token' => $tok, 'code' => 'ZZZZZZZZ'], $jh);
T::eq(404, $admin->status, 'kode tak dikenal -> 404');
$legacyCode = $regs[0]['code'];
$admin->post('/admin/checkin', ['_token' => $tok, 'code' => $legacyCode, 'event_id' => $eventId], $jh);
T::eq(409, $admin->status, 'tiket event lain ditolak saat filter event aktif');
T::eq('wrong_event', $admin->json()['status'] ?? '', 'status wrong_event');
$admin->post('/admin/checkin', ['code' => $code], $jh);
T::eq(419, $admin->status, 'check-in tanpa CSRF ditolak');
$admin->get('/admin/checkin/cari?q=peserta&event=' . $eventId, $jh);
T::eq(2, count($admin->json()['results'] ?? []), 'pencarian cepat check-in');
T::ok(str_contains($admin->json()['results'][0]['wa'] ?? '', '•'), 'nomor WA disamarkan di hasil pencarian');
$guest->get('/t/' . $code);
T::contains('Sudah hadir', $guest->body, 'tiket menampilkan status hadir');

// Toggle via form
$admin->get('/admin/peserta/' . $budiId);
$admin->post('/admin/peserta/' . $budiId . '/checkin', ['_token' => $admin->csrf()]);
$admin->follow();
T::contains('dibatalkan', $admin->body, 'batalkan check-in');
T::eq(null, $pdo->query("SELECT checked_in_at FROM registrations WHERE id = {$budiId}")->fetchColumn() ?: null, 'status hadir dihapus');

// =====================================================================
T::group('Export');
$admin->get('/admin/peserta/export?event=' . $eventId);
T::eq(200, $admin->status, 'export xlsx');
T::contains('spreadsheetml', $admin->header('content-type'), 'content-type xlsx');
T::contains('.xlsx', $admin->header('content-disposition'), 'nama file .xlsx');
$tmp = tempnam(sys_get_temp_dir(), 'x');
file_put_contents($tmp, $admin->body);
$zip = new ZipArchive();
T::ok($zip->open($tmp) === true, 'file xlsx valid');
$sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
T::contains('Budi Santoso', $sheet, 'data peserta ada di xlsx');
T::contains('Ukuran Kaos', $sheet, 'kolom field tambahan jadi header');
T::contains('Web, AI', $sheet, 'nilai checkbox digabung');
$zip->close();
unlink($tmp);
$admin->get('/admin/peserta/export?format=csv&event=' . $eventId);
T::eq(200, $admin->status, 'export CSV');
T::ok(str_starts_with($admin->body, "\xEF\xBB\xBF"), 'CSV memakai BOM UTF-8 (Excel-friendly)');
T::contains("'=HYPERLINK", $admin->body, 'formula injection dinetralkan di CSV');
T::contains('6281234567890', $admin->body, 'nomor WA di CSV');
$admin->get('/admin/peserta/export?format=csv&q=santoso');
T::notContains('Peserta 0', $admin->body, 'export mengikuti filter pencarian');

// =====================================================================
T::group('Edit & hapus peserta');
$admin->get('/admin/peserta/' . $budiId . '/edit');
T::eq(200, $admin->status, 'form edit peserta');
$admin->post('/admin/peserta/' . $budiId, ['_token' => $admin->csrf(), 'name' => 'Budi S.', 'wa' => '081234567890', 'email' => 'bukan-email', 'event_id' => $eventId]);
$admin->follow();
T::contains('Email harus berupa alamat email', $admin->body, 'validasi edit');
$admin->post('/admin/peserta/' . $budiId, ['_token' => $admin->csrf(), 'name' => 'Budi S.', 'wa' => '081234567890', 'email' => 'b@x.id',
    'event_id' => $eventId, 'extra' => ['jabatan' => 'Dosen', 'ukuran_kaos' => 'L', 'minat' => ['AI', 'Hack']]]);
$admin->follow();
T::contains('Data peserta diperbarui', $admin->body, 'edit tersimpan');
$ex = json_decode((string) $pdo->query("SELECT extra FROM registrations WHERE id = {$budiId}")->fetchColumn(), true);
T::eq(['AI'], $ex['minat'] ?? null, 'opsi checkbox di luar daftar dibuang saat edit');

$ids = $pdo->query("SELECT id FROM registrations WHERE event_id = {$eventId} AND name LIKE 'Peserta %'")->fetchAll(PDO::FETCH_COLUMN);
$admin->get('/admin/peserta');
$admin->post('/admin/peserta-massal', ['_token' => $admin->csrf(), 'action' => 'checkin', 'ids' => $ids]);
$admin->follow();
T::contains('2 peserta ditandai hadir', $admin->body, 'check-in massal');
$admin->post('/admin/peserta-massal', ['_token' => $admin->csrf(), 'action' => 'delete', 'ids' => $ids]);
$admin->follow();
T::contains('2 peserta dihapus', $admin->body, 'hapus massal');
$admin->post('/admin/peserta-massal', ['_token' => $admin->csrf(), 'action' => 'hack', 'ids' => [1]]);
T::eq(400, $admin->status, 'aksi massal tak dikenal ditolak');

// =====================================================================
T::group('Pengguna & hak akses');
$admin->get('/admin/pengguna');
T::eq(200, $admin->status, 'halaman pengguna');
$admin->post('/admin/pengguna', ['_token' => $admin->csrf(), 'name' => 'Staf Meja', 'username' => 'staf1', 'email' => '', 'role' => 'staff', 'password' => 'Staf12345']);
$admin->follow();
T::contains('Pengguna staf1 ditambahkan', $admin->body, 'staf dibuat');
$admin->post('/admin/pengguna', ['_token' => $admin->csrf(), 'name' => 'X', 'username' => 'staf1', 'role' => 'staff', 'password' => 'Staf12345']);
$admin->follow();
T::contains('Username sudah dipakai', $admin->body, 'username duplikat ditolak');
$adminId = (int) $pdo->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();
$admin->post('/admin/pengguna/' . $adminId, ['_token' => $admin->csrf(), 'role' => 'staff', 'is_active' => '1']);
$admin->follow();
T::contains('tidak dapat menurunkan peran', $admin->body, 'admin tidak bisa menurunkan diri sendiri');
$admin->post('/admin/pengguna/' . $adminId . '/hapus', ['_token' => $admin->csrf()]);
$admin->follow();
T::contains('tidak dapat menghapus akun sendiri', $admin->body, 'admin tidak bisa menghapus diri sendiri');

$staff = new Http($BASE);
$staff->get('/admin/login');
$staff->post('/admin/login', ['_token' => $staff->csrf(), 'username' => 'staf1', 'password' => 'Staf12345']);
$staff->follow();
T::eq(200, $staff->status, 'staf bisa login');
T::notContains('/admin/pengaturan', $staff->body, 'menu admin disembunyikan untuk staf');
$staff->get('/admin/peserta');
T::eq(200, $staff->status, 'staf bisa lihat peserta');
foreach (['/admin/event', '/admin/pengguna', '/admin/pengaturan', '/admin/aktivitas', '/admin/peserta/export'] as $u) {
    $staff->get($u);
    T::eq(403, $staff->status, "staf ditolak di {$u}");
}
$staff->get('/admin/peserta');
$staff->post('/admin/peserta/' . $budiId . '/hapus', ['_token' => $staff->csrf()]);
T::eq(403, $staff->status, 'staf tidak bisa menghapus peserta');
$staff->get('/admin/checkin');
$staff->post('/admin/checkin', ['_token' => $staff->csrf(), 'code' => $code], $jh);
T::eq(true, $staff->json()['ok'] ?? null, 'staf bisa check-in');

// Nonaktifkan staf -> sesi langsung berakhir
$stafId = (int) $pdo->query("SELECT id FROM users WHERE username='staf1'")->fetchColumn();
$admin->get('/admin/pengguna');
$admin->post('/admin/pengguna/' . $stafId, ['_token' => $admin->csrf(), 'role' => 'staff', 'password' => '']);
$admin->follow();
T::contains('diperbarui', $admin->body, 'staf dinonaktifkan');
$staff->get('/admin/peserta');
T::eq(302, $staff->status, 'sesi staf nonaktif langsung berakhir');

// =====================================================================
T::group('Pengaturan');
$admin->get('/admin/pengaturan');
T::eq(200, $admin->status, 'halaman pengaturan');
$settings = ['_token' => $admin->csrf(), 'app_name' => 'Presensi Keren', 'org_name' => 'DMI', 'tagline' => 'Tagline baru', 'footer_text' => '',
    'home_mode' => 'event', 'home_event_id' => '', 'wa_country_code' => '62', 'submit_limit' => '30', 'default_theme' => 'sunset', 'show_count_public' => '1'];
$admin->post('/admin/pengaturan', $settings);
$admin->follow();
T::contains('Pilih event untuk halaman depan', $admin->body, 'mode satu event wajib pilih event');
$admin->post('/admin/pengaturan', ['_token' => $admin->csrf(), 'home_event_id' => (string) $eventId] + $settings);
$admin->follow();
T::contains('Pengaturan disimpan', $admin->body, 'pengaturan tersimpan');
$web->get('/');
T::ok(str_ends_with($web->location(), '/e/workshop-ai-2026'), 'beranda langsung ke event terpilih', $web->location());
$admin->post('/admin/pengaturan', ['_token' => $admin->csrf(), 'home_mode' => 'list'] + $settings);
$web->get('/');
T::contains('Presensi Keren', $web->body, 'nama aplikasi baru tampil');

// =====================================================================
T::group('Notifikasi peserta (WA gateway, email SMTP, webhook)');
$MOCK = getenv('MOCK_API') ?: 'http://127.0.0.1:8099';
$mockLog = getenv('MOCK_LOG') ?: sys_get_temp_dir() . '/presensi-mock-api.jsonl';
$smtpLog = getenv('MOCK_SMTP_LOG') ?: sys_get_temp_dir() . '/presensi-mock-smtp.log';
@unlink($mockLog);
@unlink($smtpLog);
$readJsonl = static function (string $f): array {
    $out = [];
    foreach (is_file($f) ? file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $l) { $out[] = json_decode($l, true); }
    return $out;
};
// Tulis config/env.php lalu tunggu revalidasi OPcache server (revalidate_freq default 2 detik)
$writeEnv = static function (array $env) use ($APP): void {
    file_put_contents($APP . '/config/env.php', "<?php\nreturn " . var_export($env, true) . ";\n");
    touch($APP . '/config/env.php', time() + 1);
    sleep(3);
};
// Arahkan endpoint Fonnte ke server tiruan (config/env.php, seperti override di produksi)
$envArr = require $APP . '/config/env.php';
$envArr['NOTIFY_FONNTE_URL'] = $MOCK . '/send';
$writeEnv($envArr);

// Simulasi upgrade dari v2.0 (tabel notifications belum ada) -> migrasi otomatis
$pdo->exec('DROP TABLE notifications');
$pdo->exec("DELETE FROM app_settings WHERE `key` = 'schema_version'");
$anon = new Http($BASE);
$anon->get('/');
T::eq(200, $anon->status, 'situs tetap jalan saat upgrade');
T::eq(1, (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'notifications'")->fetchColumn(), 'tabel notifications dibuat otomatis (migrasi)');
T::eq('2', (string) $pdo->query("SELECT value FROM app_settings WHERE `key`='schema_version'")->fetchColumn(), 'versi skema tercatat');
$anon->get('/admin/notifikasi');
T::eq(302, $anon->status, 'halaman notifikasi butuh login');
$admin->get('/admin/notifikasi');
T::eq(200, $admin->status, 'halaman notifikasi tampil untuk admin');
T::contains('integrasi sistem eksternal', $admin->body, 'peringatan pengiriman data ke pihak ketiga tampil');
T::contains('Notifikasi', $admin->body, 'menu notifikasi ada di sidebar');
T::eq(0, (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn(), 'default: tidak ada notifikasi (fitur nonaktif)');

$notif = [
    'notify_enabled' => '1', 'notify_wa_provider' => 'fonnte', 'notify_wablas_domain' => '', 'notify_wa_token' => '',
    'notify_wa_template' => "Halo {nama}, tiket {event}: {kode}\n{link_tiket}\nGrup: {link_grup}",
    'notify_email_enabled' => '1', 'notify_email_driver' => 'smtp', 'notify_smtp_host' => '127.0.0.1', 'notify_smtp_port' => '2525',
    'notify_smtp_encryption' => 'none', 'notify_smtp_username' => 'mailer@test.id', 'notify_smtp_password' => 'SmtpPass!9',
    'notify_mail_from' => 'noreply@test.id', 'notify_mail_from_name' => 'Panitia DMI', 'notify_email_subject' => 'Tiket {event}',
    'notify_email_template' => "Halo {nama}\nKode: {kode}\n{link_tiket}",
    'notify_webhook_enabled' => '1', 'notify_webhook_url' => 'http://example.com/hook', 'notify_webhook_secret' => 'whsec_123',
];
$admin->post('/admin/notifikasi', ['_token' => $admin->csrf()] + $notif);
$admin->follow();
T::contains('Token API WhatsApp wajib diisi', $admin->body, 'token WA wajib bila provider dipilih');
T::contains('wajib memakai https://', $admin->body, 'webhook non-https ditolak');
$admin->post('/admin/notifikasi', ['_token' => $admin->csrf(), 'notify_wa_provider' => 'wablas', 'notify_wa_token' => 'x'] + $notif);
$admin->follow();
T::contains('Domain server Wablas wajib diisi', $admin->body, 'domain Wablas wajib');

$admin->post('/admin/notifikasi', ['_token' => $admin->csrf(), 'notify_wa_token' => 'TOKEN-RAHASIA-123', 'notify_webhook_url' => $MOCK . '/hook'] + $notif);
$admin->follow();
T::contains('Pengaturan notifikasi disimpan', $admin->body, 'pengaturan notifikasi tersimpan');
T::notContains('TOKEN-RAHASIA-123', $admin->body, 'token tidak ditampilkan ulang di halaman');
T::notContains('SmtpPass!9', $admin->body, 'password SMTP tidak ditampilkan ulang');
$stored = (string) $pdo->query("SELECT value FROM app_settings WHERE `key`='notify_wa_token'")->fetchColumn();
T::ok(str_starts_with($stored, 'enc:v1:') && !str_contains($stored, 'TOKEN-RAHASIA'), 'token disimpan terenkripsi di database');
T::contains('tersimpan (terenkripsi)', $admin->body, 'indikator rahasia tersimpan');

// Event baru dengan kolom email
$admin->get('/admin/event/baru');
$admin->post('/admin/event', ['_token' => $admin->csrf(), 'title' => 'Webinar Notifikasi', 'slug' => '', 'status' => 'open', 'theme' => 'aurora',
    'group_link' => 'https://chat.whatsapp.com/NOTIF', 'show_email' => '1', 'fields' => '[]', 'location' => 'Zoom', 'starts_at' => '2026-12-01T19:00', 'dedupe_wa' => '1']);
T::eq(302, $admin->status, 'event notifikasi dibuat');
$ng = new Http($BASE);
$ng->get('/e/webinar-notifikasi');
$nts = $ng->field('ts');
sleep(2);
$ng->post('/e/webinar-notifikasi', ['_token' => $ng->csrf(), 'ts' => $nts, 'name' => 'Rina Notif', 'wa' => '0812 9999 0001', 'email' => 'rina@peserta.id']);
T::ok(str_contains($ng->location(), '/t/'), 'pendaftaran tetap sukses dengan notifikasi aktif', $ng->location());
preg_match('#/t/([A-Z0-9]{8})#', $ng->location(), $nm);
$ncode = $nm[1] ?? '';
usleep(500000);
$rows = $pdo->query("SELECT channel, status, recipient, attempts, last_error FROM notifications ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
T::eq(3, count($rows), '3 notifikasi dibuat (WA, email, webhook)');
T::eq(['sent', 'sent', 'sent'], array_column($rows, 'status'), 'semua notifikasi terkirim', json_encode($rows));
$calls = $readJsonl($mockLog);
$wa = array_values(array_filter($calls, static fn($c) => $c['path'] === '/send'))[0] ?? [];
T::eq('TOKEN-RAHASIA-123', $wa['headers']['authorization'] ?? '', 'API Fonnte menerima token (header Authorization)');
T::eq('6281299990001', $wa['post']['target'] ?? '', 'nomor tujuan WA ternormalisasi');
T::contains('Halo Rina Notif, tiket Webinar Notifikasi: ' . $ncode, $wa['post']['message'] ?? '', 'pesan WA memakai template + placeholder');
T::contains('/t/' . $ncode, $wa['post']['message'] ?? '', 'link tiket ada di pesan');
T::contains('Grup: https://chat.whatsapp.com/NOTIF', $wa['post']['message'] ?? '', 'link grup ada di pesan');
$hook = array_values(array_filter($calls, static fn($c) => $c['path'] === '/hook'))[0] ?? [];
$hb = json_decode($hook['body'] ?? '', true);
T::eq('registration.created', $hb['event'] ?? '', 'webhook: jenis event');
T::eq($ncode, $hb['registration']['code'] ?? '', 'webhook: data pendaftaran');
T::eq('sha256=' . hash_hmac('sha256', $hook['body'] ?? '', 'whsec_123'), $hook['headers']['x-presensi-signature'] ?? '', 'webhook ditandatangani HMAC-SHA256 yang valid');
$mails = $readJsonl($smtpLog);
T::eq(1, count($mails), 'email terkirim lewat SMTP');
T::eq('RCPT TO:<rina@peserta.id>', $mails[0]['to'] ?? '', 'email ke alamat peserta');
T::eq('SmtpPass!9', $mails[0]['pass'] ?? '', 'login SMTP memakai password yang didekripsi');
T::contains('Subject: Tiket Webinar Notifikasi', $mails[0]['data'] ?? '', 'subjek email dari template');
T::contains(base64_encode("Halo Rina Notif\nKode: " . $ncode), str_replace("\r\n", '', $mails[0]['data'] ?? ''), 'isi email dari template');

// Tanpa email -> hanya WA & webhook
$ng2 = new Http($BASE);
$ng2->get('/e/webinar-notifikasi');
$nts2 = $ng2->field('ts');
// Gagal kirim WA -> antre ulang, lalu sukses setelah diperbaiki
$envArr['NOTIFY_FONNTE_URL'] = $MOCK . '/fail';
$writeEnv($envArr);
sleep(2);
$ng2->post('/e/webinar-notifikasi', ['_token' => $ng2->csrf(), 'ts' => $nts2, 'name' => 'Tono Gagal', 'wa' => '081299990002', 'email' => '']);
usleep(500000);
$fail = $pdo->query("SELECT * FROM notifications WHERE channel='whatsapp' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
T::eq(2, (int) $pdo->query("SELECT COUNT(*) FROM notifications n JOIN registrations r ON r.id=n.registration_id WHERE r.name='Tono Gagal'")->fetchColumn(), 'tanpa email: hanya WA + webhook');
T::eq('pending', $fail['status'] ?? '', 'WA gagal -> dijadwalkan ulang (bukan hilang)');
T::contains('token invalid', (string) ($fail['last_error'] ?? ''), 'alasan gagal dari API dicatat');
T::eq(1, (int) ($fail['attempts'] ?? 0), 'jumlah percobaan tercatat');
$admin->get('/admin/notifikasi?status=pending');
T::contains('token invalid', $admin->body, 'riwayat menampilkan error');
T::contains('Kirim ulang', $admin->body, 'tombol kirim ulang tersedia');
$envArr['NOTIFY_FONNTE_URL'] = $MOCK . '/send';
$writeEnv($envArr);
$admin->post('/admin/notifikasi/' . $fail['id'] . '/ulang', ['_token' => $admin->csrf()]);
$admin->follow();
T::contains('Notifikasi terkirim ulang', $admin->body, 'kirim ulang manual berhasil');
T::eq('sent', $pdo->query('SELECT status FROM notifications WHERE id = ' . (int) $fail['id'])->fetchColumn(), 'status menjadi terkirim');

// Kirim tes
$admin->post('/admin/notifikasi/tes', ['_token' => $admin->csrf(), 'channel' => 'whatsapp', 'target' => '081211112222']);
$admin->follow();
T::contains('Tes whatsapp berhasil', $admin->body, 'kirim tes WhatsApp');
$last = $readJsonl($mockLog);
T::contains('[TES]', end($last)['post']['message'] ?? '', 'pesan tes ditandai [TES]');
$admin->post('/admin/notifikasi/tes', ['_token' => $admin->csrf(), 'channel' => 'email', 'target' => 'bukan-email']);
$admin->follow();
T::contains('Tes email gagal', $admin->body, 'tes email ke alamat tidak valid ditolak');

// Simpan ulang tanpa isi token -> token lama tetap dipakai
$admin->post('/admin/notifikasi', ['_token' => $admin->csrf(), 'notify_wa_token' => '', 'notify_smtp_password' => '', 'notify_webhook_secret' => '', 'notify_webhook_url' => $MOCK . '/hook'] + $notif);
$admin->follow();
T::eq($stored !== '' , (string) $pdo->query("SELECT value FROM app_settings WHERE `key`='notify_wa_token'")->fetchColumn() !== '', 'token lama dipertahankan saat kolom dikosongkan');

// Matikan saklar utama -> tidak ada data terkirim
$admin->post('/admin/notifikasi', ['_token' => $admin->csrf(), 'notify_enabled' => '', 'notify_webhook_url' => $MOCK . '/hook'] + $notif);
$before = (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
$ng3 = new Http($BASE);
$ng3->get('/e/webinar-notifikasi');
$nts3 = $ng3->field('ts');
sleep(2);
$ng3->post('/e/webinar-notifikasi', ['_token' => $ng3->csrf(), 'ts' => $nts3, 'name' => 'Tanpa Notif', 'wa' => '081299990003', 'email' => 'x@y.id']);
T::ok(str_contains($ng3->location(), '/t/'), 'pendaftaran sukses saat notifikasi mati');
T::eq($before, (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn(), 'saklar utama mati: tidak ada notifikasi dibuat');

// =====================================================================
T::group('Status, duplikat & hapus event');
$admin->get('/admin/event');
$admin->post('/admin/event/' . $eventId . '/status', ['_token' => $admin->csrf(), 'status' => 'draft']);
$admin->follow();
$web2 = new Http($BASE);
$web2->get('/e/workshop-ai-2026');
T::eq(404, $web2->status, 'event draft tidak terlihat publik');
$admin->get('/e/workshop-ai-2026');
T::eq(200, $admin->status, 'admin bisa pratinjau event draft');
T::contains('Pratinjau draft', $admin->body, 'label pratinjau');
$admin->post('/admin/event/' . $eventId . '/status', ['_token' => $admin->csrf(), 'status' => 'bogus']);
T::eq(400, $admin->status, 'status tak dikenal ditolak');
$admin->get('/admin/event');
$admin->post('/admin/event/' . $eventId . '/duplikat', ['_token' => $admin->csrf()]);
T::ok((bool) preg_match('#/admin/event/(\d+)/edit$#', $admin->location(), $m2), 'event diduplikasi');
$dupSlug = $pdo->query('SELECT slug FROM events WHERE id = ' . (int) ($m2[1] ?? 0))->fetchColumn();
T::eq('workshop-ai-2026-salinan', $dupSlug, 'slug salinan unik');
$admin->follow();
$admin->post('/admin/event/' . $eventId . '/hapus', ['_token' => $admin->csrf(), 'confirm' => 'salah']);
$admin->follow();
T::contains('Konfirmasi tidak cocok', $admin->body, 'hapus event butuh konfirmasi slug');
$admin->post('/admin/event/' . $eventId . '/hapus', ['_token' => $admin->csrf(), 'confirm' => 'workshop-ai-2026']);
$admin->follow();
T::contains('dihapus', $admin->body, 'event dihapus');
T::eq(0, (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE event_id = {$eventId}")->fetchColumn(), 'peserta ikut terhapus (cascade)');

// =====================================================================
T::group('Keamanan request');
$admin->get('/admin/pengaturan');
$admin->post('/admin/pengaturan', ['_token' => $admin->csrf()] + $settings, ['Origin: https://evil.example']);
T::eq(403, $admin->status, 'POST dengan Origin asing ditolak');
$x = new Http($BASE);
$x->post('/admin/logout', []);
T::eq(419, $x->status, 'logout tanpa CSRF ditolak (anti logout-CSRF)');
foreach (['/../config/env.php', '/config/env.php', '/storage/installed.lock', '/.htaccess', '/index.php/../../config/env.php'] as $u) {
    $x->get($u);
    T::notContains('DB_PASSWORD', $x->body, "file rahasia tidak bocor via {$u}");
}
$admin->get('/admin/aktivitas');
T::eq(200, $admin->status, 'log aktivitas tampil');
T::contains('login_failed', $admin->body, 'percobaan login gagal tercatat');
T::contains('Export', $admin->body, 'aktivitas export tercatat');

// =====================================================================
T::group('Akun & logout');
$admin2 = new Http($BASE);
$admin2->get('/admin/login');
$admin2->post('/admin/login', ['_token' => $admin2->csrf(), 'username' => 'admin', 'password' => 'Rahasia123']);
$admin2->follow();
T::eq(200, $admin2->status, 'login sesi kedua');
$admin->get('/admin/akun');
$admin->post('/admin/akun', ['_token' => $admin->csrf(), 'name' => 'Super Admin', 'email' => '', 'current_password' => 'salah', 'password' => 'BaruSekali9', 'password_confirmation' => 'BaruSekali9']);
$admin->follow();
T::contains('Password saat ini salah', $admin->body, 'ganti password butuh password lama');
$admin->post('/admin/akun', ['_token' => $admin->csrf(), 'name' => 'Super Admin', 'email' => '', 'current_password' => 'Rahasia123', 'password' => 'BaruSekali9', 'password_confirmation' => 'BaruSekali9']);
$admin->follow();
T::contains('Profil diperbarui', $admin->body, 'password diganti');
$admin->get('/admin');
T::eq(200, $admin->status, 'sesi saat ini tetap login');
$admin2->get('/admin');
T::eq(302, $admin2->status, 'sesi lain otomatis logout setelah ganti password');
$admin->post('/admin/logout', ['_token' => $admin->csrf()]);
T::eq(302, $admin->status, 'logout');
$admin->get('/admin');
T::eq(302, $admin->status, 'setelah logout tidak bisa akses admin');

$errLogs = glob($APP . '/storage/logs/app-*.log') ?: [];
$logText = '';
foreach ($errLogs as $lf) {
    $logText .= (string) file_get_contents($lf);
}
T::ok(!preg_match('/(ErrorException|TypeError|Error:|PDOException)/', $logText), 'tidak ada error/exception di log aplikasi', substr($logText, 0, 1500));

exit(T::summary());
