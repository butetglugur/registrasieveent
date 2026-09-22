<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Setting;
use App\Models\User;
use PDO;
use Throwable;

/**
 * Wizard instalasi berbasis web. Terkunci otomatis setelah selesai
 * (file storage/installed.lock). Hapus file itu hanya jika ingin instal ulang.
 */
final class InstallController extends Controller
{
    private function guard(): void
    {
        if (is_installed()) {
            throw new HttpException(404);
        }
    }

    public function requirements(): array
    {
        return [
            ['PHP 8.0 atau lebih baru (terdeteksi ' . PHP_VERSION . ')', PHP_VERSION_ID >= 80000],
            ['Ekstensi PDO MySQL', extension_loaded('pdo_mysql')],
            ['Ekstensi mbstring', extension_loaded('mbstring')],
            ['Ekstensi JSON', function_exists('json_encode')],
            ['Folder storage/ dapat ditulis', is_writable(storage_path())],
            ['Folder storage/sessions dapat ditulis', is_writable(storage_path('sessions'))],
            ['Folder storage/logs dapat ditulis', is_writable(storage_path('logs'))],
            ['Folder config/ dapat ditulis (untuk menyimpan konfigurasi)', is_writable(base_path('config'))],
        ];
    }

    public function index(Request $request): string
    {
        $this->guard();
        return view('install.index', [
            'requirements' => $this->requirements(),
            'detectedUrl'  => $request->origin() . Request::basePath(),
            'manualEnv'    => session()->pull('install_manual_env'),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->guard();
        $input = $request->only([
            'db_host', 'db_port', 'db_name', 'db_user', 'app_url',
            'admin_name', 'admin_username', 'app_name',
        ]);
        $input['db_pass'] = (string) $request->input('db_pass', '');
        $input['admin_password'] = (string) $request->input('admin_password', '');
        $input['admin_password_confirmation'] = (string) $request->input('admin_password_confirmation', '');
        $input['db_port'] = $input['db_port'] !== '' ? $input['db_port'] : '3306';

        $this->validate($input, [
            'db_host'        => 'required|max:100',
            'db_port'        => 'required|integer',
            'db_name'        => 'required|max:64',
            'db_user'        => 'required|max:64',
            'app_name'       => 'required|max:60',
            'app_url'        => 'nullable|url|max:200',
            'admin_name'     => 'required|max:100',
            'admin_username' => 'required|username',
            'admin_password' => 'required|password|same:admin_password_confirmation',
        ], [
            'db_host' => 'Host database', 'db_port' => 'Port', 'db_name' => 'Nama database',
            'db_user' => 'User database', 'app_name' => 'Nama aplikasi', 'app_url' => 'URL aplikasi',
            'admin_name' => 'Nama admin', 'admin_username' => 'Username admin', 'admin_password' => 'Password admin',
        ], route('install'));

        $cfg = [
            'host' => $input['db_host'], 'port' => (int) $input['db_port'], 'database' => $input['db_name'],
            'username' => $input['db_user'], 'password' => $input['db_pass'], 'charset' => 'utf8mb4',
        ];

        try {
            $pdo = DB::connect($cfg);
        } catch (Throwable $e) {
            return Response::redirect(route('install'))
                ->withErrors([], $input)
                ->with('error', 'Gagal terhubung ke database: ' . $this->cleanDbError($e->getMessage())
                    . ' — periksa nama database, user, password, dan pastikan user sudah diberi akses (ALL PRIVILEGES) ke database tersebut.');
        }

        DB::setPdo($pdo);
        $imported = null;
        try {
            DB::runSqlFile(base_path('database/schema.sql'));

            $existing = User::findByUsername($input['admin_username']);
            if ($existing) {
                User::update((int) $existing['id'], [
                    'name' => $input['admin_name'], 'role' => 'admin', 'is_active' => 1,
                    'password_hash' => password_hash($input['admin_password'], PASSWORD_DEFAULT),
                ]);
                $adminId = (int) $existing['id'];
            } else {
                $adminId = User::create([
                    'name' => $input['admin_name'], 'username' => $input['admin_username'], 'email' => '',
                    'password' => $input['admin_password'], 'role' => 'admin',
                ]);
            }

            Setting::flush();
            if (Setting::get('app_name', '') === '' || $request->str('app_name') !== '') {
                Setting::set('app_name', $input['app_name']);
            }

            if ($request->bool('import_legacy')) {
                $imported = self::importLegacy($adminId);
            }

            if ((int) DB::value('SELECT COUNT(*) FROM events') === 0) {
                self::createSampleEvent($adminId);
            }
            ActivityLog::record('install', 'Aplikasi diinstal', $adminId);
        } catch (Throwable $e) {
            \App\Core\ErrorHandler::log($e);
            return Response::redirect(route('install'))
                ->withErrors([], $input)
                ->with('error', 'Gagal menyiapkan tabel database: ' . $this->cleanDbError($e->getMessage()));
        }

        $env = [
            'APP_URL'      => rtrim($input['app_url'], '/'),
            'APP_KEY'      => bin2hex(random_bytes(32)),
            'APP_DEBUG'    => false,
            'APP_TIMEZONE' => 'Asia/Jakarta',
            'DB_HOST'      => $cfg['host'],
            'DB_PORT'      => $cfg['port'],
            'DB_DATABASE'  => $cfg['database'],
            'DB_USERNAME'  => $cfg['username'],
            'DB_PASSWORD'  => $cfg['password'],
        ];

        if (!Config::writeEnv($env)) {
            session()->put('install_manual_env', Config::renderEnv($env));
            return Response::redirect(route('install'))->with(
                'warning',
                'Database siap, tetapi file config/env.php tidak dapat ditulis otomatis. Buat file tersebut secara manual '
                . 'lewat File Manager dengan isi di bawah, lalu buat file kosong storage/installed.lock.'
            );
        }

        @file_put_contents(storage_path('installed.lock'), 'Terinstal pada ' . date('c') . "\n");

        $msg = 'Instalasi berhasil! Silakan login dengan akun admin yang baru dibuat.';
        if ($imported) {
            $msg .= sprintf(' Data lama berhasil diimpor: %d peserta, %d admin.', $imported['registrations'], $imported['users']);
        }
        return Response::redirect(route('login'))->with('success', $msg);
    }

    private function cleanDbError(string $message): string
    {
        // Jangan tampilkan password atau DSN.
        $message = preg_replace('/\(using password: \w+\)/i', '', $message) ?? $message;
        $message = preg_replace('/SQLSTATE\[[^\]]+\]\s*(\[\d+\])?\s*/', '', $message) ?? $message;
        return trim(mb_substr($message, 0, 200));
    }

    /**
     * Impor data dari versi lama (tabel attendees, settings, admins) bila ada.
     * @return array{registrations:int,users:int}|null
     */
    public static function importLegacy(?int $adminId): ?array
    {
        if (Setting::get('legacy_imported', '') === '1' || !DB::tableExists('attendees')) {
            return null;
        }
        $result = ['registrations' => 0, 'users' => 0];

        $old = [];
        if (DB::tableExists('settings')) {
            try {
                $old = DB::first('SELECT * FROM settings ORDER BY id ASC LIMIT 1') ?? [];
            } catch (Throwable $e) {
                $old = [];
            }
        }
        $fields = [];
        $rawFields = json_decode((string) ($old['form_fields'] ?? '[]'), true);
        if (is_array($rawFields)) {
            foreach ($rawFields as $f) {
                if (is_array($f)) {
                    $fields[] = ['key' => $f['key'] ?? '', 'label' => $f['label'] ?? '', 'type' => 'text', 'required' => !empty($f['required'])];
                }
            }
        }

        DB::transaction(static function () use ($old, $fields, $adminId, &$result) {
            $eventId = Event::create([
                'slug'        => Event::uniqueSlug('seminar'),
                'title'       => 'Seminar Digital Media Inspirasi',
                'subtitle'    => 'Data dari aplikasi versi lama',
                'description' => 'Silakan isi data agar bisa langsung bergabung ke grup seminar.',
                'status'      => 'open',
                'group_link'  => safe_url((string) ($old['group_link'] ?? '')) ?: null,
                'theme'       => 'violet',
                'fields'      => json_encode(Event::sanitizeFields($fields), JSON_UNESCAPED_UNICODE),
                'show_address' => 1,
                'show_representative' => 1,
                'dedupe_wa'   => 0,
            ], $adminId);

            $stmt = DB::run('SELECT * FROM attendees ORDER BY id ASC');
            while ($a = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $wa = normalize_wa((string) ($a['wa'] ?? ''));
                $created = (string) ($a['created_at'] ?? '') ?: now();
                $row = [
                    'event_id'       => $eventId,
                    'name'           => mb_substr(trim((string) ($a['name'] ?? '-')) ?: '-', 0, 120),
                    'wa'             => mb_substr($wa !== '' ? $wa : '-', 0, 20),
                    'address'        => mb_substr((string) ($a['address'] ?? ''), 0, 255) ?: null,
                    'representative' => mb_substr((string) ($a['representative'] ?? ''), 0, 150) ?: null,
                    'extra'          => ($a['extra'] ?? null) ?: null,
                ];
                $r = Registration::create($row);
                DB::run('UPDATE registrations SET created_at = ?, updated_at = ? WHERE id = ?', [$created, $created, $r['id']]);
                $result['registrations']++;
            }

            if (DB::tableExists('admins')) {
                foreach (DB::select('SELECT * FROM admins') as $adm) {
                    $username = (string) ($adm['username'] ?? '');
                    $hash = (string) ($adm['password_hash'] ?? '');
                    if ($username === '' || $hash === '' || User::findByUsername($username)) {
                        continue;
                    }
                    DB::insert('users', [
                        'name' => $username, 'username' => mb_substr($username, 0, 40), 'email' => null,
                        'password_hash' => $hash, 'role' => 'admin', 'is_active' => 1,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $result['users']++;
                }
            }
            Setting::set('legacy_imported', '1');
        });

        return $result;
    }

    private static function createSampleEvent(int $adminId): void
    {
        Event::create([
            'slug'        => 'seminar-contoh',
            'title'       => 'Seminar Contoh',
            'subtitle'    => 'Ubah atau hapus event ini dari panel admin',
            'description' => "Isi formulir untuk konfirmasi kehadiran.\nSetelah terdaftar Anda akan mendapatkan tiket QR dan tautan grup WhatsApp.",
            'location'    => 'Aula Utama',
            'starts_at'   => date('Y-m-d 09:00:00', strtotime('+7 days')),
            'ends_at'     => date('Y-m-d 12:00:00', strtotime('+7 days')),
            'status'      => 'draft',
            'theme'       => 'violet',
            'fields'      => json_encode(Event::sanitizeFields([
                ['label' => 'Sumber informasi', 'type' => 'select', 'options' => ['Instagram', 'WhatsApp', 'Teman', 'Lainnya']],
            ]), JSON_UNESCAPED_UNICODE),
        ], $adminId);
    }
}
