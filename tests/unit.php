<?php
/**
 * Unit test: helper, validator, router, model util, xlsx writer.
 * Jalankan: php tests/unit.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

define('BASE_PATH', realpath(__DIR__ . '/../presensi'));
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'example.test';
require BASE_PATH . '/bootstrap/app.php';

use App\Controllers\Admin\CheckinController;
use App\Controllers\EventController;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Router;
use App\Core\Validator;
use App\Core\XlsxWriter;
use App\Models\Event;

T::group('Helper: nomor WhatsApp');
T::eq('6281234567890', normalize_wa('081234567890'), '08xx -> 628xx');
T::eq('6281234567890', normalize_wa('+62 812-3456-7890'), '+62 dengan spasi/strip');
T::eq('6281234567890', normalize_wa('81234567890'), '8xx tanpa awalan');
T::eq('60123456789', normalize_wa('0060123456789'), 'awalan 00 internasional');
T::eq('', normalize_wa('abc'), 'bukan angka -> kosong');
T::ok(valid_wa('6281234567890'), 'nomor valid diterima');
T::ok(!valid_wa('62812'), 'nomor terlalu pendek ditolak');
T::ok(!valid_wa('0812345678901234567'), 'nomor terlalu panjang ditolak');
T::eq('6281••••••890', mask_wa('6281234567890'), 'mask nomor WA');

T::group('Helper: keamanan output');
T::eq('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', e("<script>alert('x')</script>"), 'e() escape HTML & kutip');
T::eq('', e(null), 'e(null) -> string kosong');
T::eq("'=HYPERLINK(\"x\")", csv_safe('=HYPERLINK("x")'), 'csv_safe mencegah formula injection (=)');
T::eq("'+cmd", csv_safe('+cmd'), 'csv_safe (+)');
T::eq("'@SUM(1)", csv_safe('@SUM(1)'), 'csv_safe (@)');
T::eq('Budi', csv_safe('Budi'), 'csv_safe teks biasa tidak berubah');
T::eq('', safe_url('javascript:alert(1)'), 'safe_url tolak javascript:');
T::eq('', safe_url('data:text/html,hi'), 'safe_url tolak data:');
T::eq('https://chat.whatsapp.com/AbC', safe_url(' https://chat.whatsapp.com/AbC '), 'safe_url terima https');
T::eq('', safe_url('chat.whatsapp.com/x'), 'safe_url tolak tanpa skema');

T::group('Helper: teks & tanggal');
T::eq('seminar-digital-2026', slugify('Seminar Digital 2026!'), 'slugify');
T::eq('cafe-creme', slugify('Café Crème'), 'slugify transliterasi');
T::eq('17 Agustus 2026, 09:30', date_id('2026-08-17 09:30:00'), 'date_id bahasa Indonesia');
T::eq('17 Agu 2026', date_id('2026-08-17', false, true), 'date_id singkat');
T::eq('Senin', day_id('2026-08-17'), 'day_id');
T::eq('1.234.567', number_id(1234567), 'number_id format ribuan');
T::eq(8, strlen(random_code(8)), 'random_code panjang 8');
T::ok((bool) preg_match('/^[A-HJ-NP-Z2-9]+$/', random_code(50)), 'random_code tanpa karakter ambigu');
T::contains('--g1:#0ea5e9', theme_style('ocean'), 'theme_style ocean');
T::contains('--g1:#7c3aed', theme_style('tidak-ada'), 'theme_style fallback violet');

T::group('Validator');
$v = Validator::make(['name' => '', 'email' => 'x'], ['name' => 'required', 'email' => 'nullable|email'], ['name' => 'Nama']);
T::ok($v->fails(), 'gagal saat field wajib kosong');
T::eq('Nama wajib diisi.', $v->errors()['name'][0] ?? '', 'pesan wajib berbahasa Indonesia');
T::ok(isset($v->errors()['email']), 'email tidak valid terdeteksi');
T::ok(Validator::make(['q' => '5'], ['q' => 'integer|numeric|min:1|max:10'])->passes(), 'angka dalam rentang lolos');
T::ok(Validator::make(['q' => '0'], ['q' => 'integer|numeric|min:1'])->fails(), 'angka di bawah minimum gagal');
T::ok(Validator::make(['q' => str_repeat('a', 11)], ['q' => 'max:10'])->fails(), 'panjang teks maksimum');
T::ok(Validator::make(['s' => 'b'], ['s' => 'in:a,b,c'])->passes(), 'aturan in lolos');
T::ok(Validator::make(['s' => 'z'], ['s' => 'in:a,b,c'])->fails(), 'aturan in gagal');
T::ok(Validator::make(['p' => 'abcdefgh'], ['p' => 'password'])->fails(), 'password tanpa angka ditolak');
T::ok(Validator::make(['p' => 'abc12345'], ['p' => 'password'])->passes(), 'password huruf+angka diterima');
T::ok(Validator::make(['p' => 'a', 'c' => 'b'], ['p' => 'same:c'])->fails(), 'konfirmasi tidak cocok');
T::ok(Validator::make(['u' => 'admin_1'], ['u' => 'username'])->passes(), 'username valid');
T::ok(Validator::make(['u' => 'ad min'], ['u' => 'username'])->fails(), 'username dengan spasi ditolak');
T::ok(Validator::make(['s' => 'abc-def'], ['s' => 'slug'])->passes(), 'slug valid');
T::ok(Validator::make(['s' => 'Abc Def'], ['s' => 'slug'])->fails(), 'slug tidak valid');
T::eq('2026-08-17 09:30:00', Validator::parseDate('2026-08-17T09:30'), 'parseDate datetime-local');
T::eq('2026-08-17', Validator::parseDate('2026-08-17'), 'parseDate tanggal');
T::eq(null, Validator::parseDate('2026-02-30'), 'parseDate tolak tanggal mustahil');
T::ok(Validator::make(['u' => 'javascript:x'], ['u' => 'url'])->fails(), 'url javascript: ditolak');

T::group('Event::sanitizeFields (input form builder tak tepercaya)');
$f = Event::sanitizeFields(json_encode([
    ['label' => 'Jabatan', 'type' => 'text', 'required' => true],
    ['label' => '<b>Ukuran</b>', 'type' => 'select', 'options' => "S\nM\nM\nL"],
    ['label' => 'Tanpa opsi', 'type' => 'radio', 'options' => []],
    ['label' => '', 'type' => 'text'],
    ['label' => 'Jabatan', 'type' => 'evil'],
    ['label' => 'Nama', 'key' => 'name'],
]));
T::eq(4, count($f), 'field kosong/tanpa opsi dibuang');
T::eq('jabatan', $f[0]['key'], 'key otomatis dari label');
T::eq('Ukuran', $f[1]['label'], 'tag HTML dibuang dari label');
T::eq(['S', 'M', 'L'], $f[1]['options'], 'opsi duplikat dibuang');
T::eq('jabatan_2', $f[2]['key'], 'key duplikat diberi akhiran');
T::eq('text', $f[2]['type'], 'tipe tidak dikenal -> text');
T::eq('f_name', $f[3]['key'], 'key terlarang (bentrok kolom bawaan) diganti');
T::eq([], Event::sanitizeFields('bukan json'), 'JSON rusak -> array kosong');

T::group('Event::registrationState');
$base = ['id' => 1, 'status' => 'open', 'closes_at' => null, 'quota' => null];
T::eq(true, Event::registrationState($base, 0)[0], 'event open bisa daftar');
T::eq(false, Event::registrationState(['status' => 'closed'] + $base, 0)[0], 'event ditutup');
T::eq(false, Event::registrationState(['status' => 'draft'] + $base, 0)[0], 'event draft');
T::eq(false, Event::registrationState(['quota' => 2] + $base, 2)[0], 'kuota penuh');
T::eq(true, Event::registrationState(['quota' => 2] + $base, 1)[0], 'kuota masih ada');
T::eq(false, Event::registrationState(['closes_at' => date('Y-m-d H:i:s', time() - 60)] + $base, 0)[0], 'lewat batas waktu');

T::group('Router');
$r = Router::fresh();
$r->get('/e/{slug}', 'x')->name('ev');
$r->post('/e/{slug}', 'y');
$r->group(['prefix' => '/admin', 'middleware' => ['auth']], function ($r) {
    $r->get('/peserta/{id}', 'z')->name('p');
});
[$route, $params] = $r->match('GET', '/e/seminar-2026');
T::eq(['slug' => 'seminar-2026'], $params, 'parameter route diambil');
[$route] = $r->match('GET', '/admin/peserta/5');
T::eq(['auth'], $route['middleware'], 'middleware grup diterapkan');
T::eq('/e/a%20b', $r->pathFor('ev', ['slug' => 'a b']), 'pathFor meng-encode parameter');
try { $r->match('GET', '/tidak-ada'); T::ok(false, '404 untuk route tak dikenal'); } catch (App\Core\HttpException $e) { T::eq(404, $e->status, '404 untuk route tak dikenal'); }
try { $r->match('PUT', '/e/x'); T::ok(false, '405'); } catch (App\Core\HttpException $e) { T::eq(405, $e->status, '405 untuk metode salah'); }

T::group('Request base path (instalasi subfolder)');
$cases = [
    ['/index.php', '/e/abc', '', '/e/abc'],
    ['/public/index.php', '/e/abc', '', '/e/abc'],             // root .htaccess -> public/
    ['/presensi/public/index.php', '/presensi/e/abc', '/presensi', '/e/abc'],
    ['/presensi/index.php', '/presensi/admin', '/presensi', '/admin'],
    ['/public/index.php', '/public/e/abc', '/public', '/e/abc'],
];
foreach ($cases as [$script, $uri, $expBase, $expPath]) {
    $_SERVER['SCRIPT_NAME'] = $script;
    $_SERVER['REQUEST_URI'] = $uri . '?x=1';
    Request::reset();
    T::eq([$expBase, $expPath], [Request::basePath(), Request::current()->path()], "SCRIPT_NAME={$script} URI={$uri}");
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';
Request::reset();
$_SERVER['HTTP_HOST'] = 'evil.com<script>';
T::ok(!str_contains(Request::current()->host() . Request::current()->origin(), '<'), 'Host header berbahaya ditolak');
$_SERVER['HTTP_HOST'] = 'example.test';

T::group('Paginator');
$p = new Paginator([], 250, 25, 5);
T::eq(10, $p->lastPage, 'jumlah halaman');
T::eq(101, $p->from(), 'from');
T::eq(125, $p->to(), 'to');
T::eq([1, null, 3, 4, 5, 6, 7, null, 10], $p->window(), 'window dengan elipsis');
T::eq(1, (new Paginator([], 0, 25, 9))->page, 'halaman di luar batas dikoreksi');

T::group('Check-in: ekstraksi kode dari QR');
T::eq('AB12CD34', CheckinController::extractCode('https://presensi.x.id/t/AB12CD34'), 'dari URL tiket');
T::eq('AB12CD34', CheckinController::extractCode('https://x.id/sub/t/ab12cd34?baru=1'), 'URL subfolder + query, huruf kecil');
T::eq('AB12CD34', CheckinController::extractCode(' ab12-cd34 '), 'kode manual dengan strip/spasi');

T::group('Anti-bot time-trap');
$ts = EventController::signTs(time() - 10);
T::ok(EventController::validTs($ts), 'token 10 detik lalu valid');
T::ok(!EventController::validTs(EventController::signTs(time())), 'token terlalu cepat (<2 detik) ditolak');
T::ok(!EventController::validTs(EventController::signTs(time() - 86400 * 3)), 'token kedaluwarsa ditolak');
T::ok(!EventController::validTs((time() - 10) . '.0000000000000000'), 'tanda tangan palsu ditolak');
T::ok(!EventController::validTs('abc'), 'format sampah ditolak');

T::group('XlsxWriter');
$x = new XlsxWriter();
$x->addRow(['No', 'Nama', 'WA'], true);
$x->addRow([1, '=CMD()<&>', '6281234567890']);
$x->addRow([2, "Kontrol\x01char", '']);
$file = $x->finish();
$zip = new ZipArchive();
T::ok($zip->open($file) === true, 'file xlsx adalah zip valid');
$sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
T::ok(simplexml_load_string($sheet) !== false, 'sheet1.xml adalah XML valid');
T::contains('=CMD()&lt;&amp;&gt;', $sheet, 'isi di-escape & disimpan sebagai teks (bukan formula)');
T::contains('<t xml:space="preserve">6281234567890</t>', $sheet, 'nomor WA tetap teks');
T::notContains("\x01", $sheet, 'karakter kontrol dibuang');
T::contains('autoFilter ref="A1:C3"', $sheet, 'autofilter aktif');
foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/styles.xml', 'xl/_rels/workbook.xml.rels'] as $part) {
    T::ok($zip->getFromName($part) !== false && simplexml_load_string((string) $zip->getFromName($part)) !== false, "bagian {$part} ada & valid");
}
$zip->close();
@unlink($file);

T::group('View engine');
App\Core\Session::instance();
$html = App\Core\View::make('partials.pagination', ['p' => new Paginator([], 100, 10, 2)]);
T::contains('aria-current="page">2<', $html, 'partial pagination render');

exit(T::summary());
