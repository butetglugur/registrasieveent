<?php
// Isi data demo (untuk screenshot/visual test). Jalankan setelah e2e.
$pdo = new PDO('mysql:host=127.0.0.1;dbname=' . (getenv('E2E_DB') ?: 'presensi_e2e') . ';charset=utf8mb4', 'presensi', 'secretpass', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$ev = (int) $pdo->query("SELECT id FROM events WHERE slug='seminar'")->fetchColumn();
$pdo->exec("UPDATE events SET starts_at = DATE_ADD(CURDATE(), INTERVAL 5 DAY) + INTERVAL 9 HOUR, location='Grand Aston Medan', quota=300, theme='violet', subtitle='Strategi konten & personal branding di era AI' WHERE id=$ev");
$names = ['Andi Pratama','Rina Kartika','Dewi Lestari','Fajar Nugroho','Putri Ayu','Rizky Ramadhan','Nur Aisyah','Bayu Saputra','Intan Permata','Hendra Wijaya','Maya Sari','Yusuf Hidayat','Sinta Dewi','Agus Salim','Lina Marlina','Taufik Hakim'];
$reps = ['Universitas Sumatera Utara','Politeknik Negeri Medan','PT Telkom Indonesia','Dinas Kominfo Sumut','UINSU','Freelancer','Bank Sumut'];
$st = $pdo->prepare('INSERT INTO registrations (event_id, code, name, wa, representative, address, extra, ip, created_at, updated_at, checked_in_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
for ($i = 0; $i < 86; $i++) {
    $d = date('Y-m-d H:i:s', time() - random_int(0, 13) * 86400 - random_int(0, 36000));
    $st->execute([$ev, strtoupper(substr(bin2hex(random_bytes(6)), 0, 8)), $names[$i % 16] . ($i >= 16 ? ' ' . chr(65 + intdiv($i, 16)) . '.' : ''), '6281' . random_int(100000000, 999999999),
        $reps[array_rand($reps)], 'Medan', '{"jabatan":"Staf"}', '127.0.0.1', $d, $d, random_int(0, 2) ? null : $d]);
}
echo "seeded\n";
