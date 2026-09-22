<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Crypt;
use App\Core\HttpClient;
use App\Core\Mailer;
use App\Models\Notification;
use App\Models\Setting;

/**
 * Notifikasi ke peserta setelah mendaftar.
 *
 * ⚠️ PERHATIAN — INTEGRASI SISTEM EKSTERNAL:
 * Kelas ini MENGIRIM DATA PESERTA (nama, nomor WA, email, kode tiket) ke layanan
 * pihak ketiga: WA gateway (Fonnte/Wablas), server SMTP, dan/atau URL webhook
 * yang diatur admin. Semua kanal NONAKTIF secara default.
 */
final class Notifier
{
    public const WA_PROVIDERS = [
        'none'   => 'Nonaktif',
        'fonnte' => 'Fonnte (fonnte.com)',
        'wablas' => 'Wablas (wablas.com)',
    ];

    public const PLACEHOLDERS = [
        '{nama}'       => 'Nama peserta',
        '{event}'      => 'Judul event',
        '{tanggal}'    => 'Tanggal & jam event',
        '{lokasi}'     => 'Lokasi',
        '{kode}'       => 'Kode tiket',
        '{link_tiket}' => 'Tautan tiket + QR',
        '{link_grup}'  => 'Link grup WhatsApp',
        '{instansi}'   => 'Instansi / perwakilan',
    ];

    public const DEFAULT_WA_TEMPLATE = "Halo {nama} 👋\n\nPendaftaran Anda untuk *{event}* berhasil.\n🗓 {tanggal}\n📍 {lokasi}\n🎫 Kode tiket: *{kode}*\n\nTiket & QR code: {link_tiket}\nGrup WhatsApp: {link_grup}\n\nTunjukkan QR saat registrasi ulang. Sampai jumpa!";
    public const DEFAULT_EMAIL_SUBJECT = 'Tiket Anda: {event}';
    public const DEFAULT_EMAIL_TEMPLATE = "Halo {nama},\n\nTerima kasih telah mendaftar di {event}.\n\nJadwal : {tanggal}\nLokasi : {lokasi}\nKode tiket : {kode}\n\nBuka tiket & QR code Anda di: {link_tiket}\nGrup WhatsApp: {link_grup}\n\nSampai jumpa!";

    /** Kunci pengaturan yang disimpan terenkripsi. */
    public const SECRET_KEYS = ['notify_wa_token', 'notify_smtp_password', 'notify_webhook_secret'];

    public static function enabled(): bool
    {
        return Setting::get('notify_enabled', '0') === '1';
    }

    public static function secret(string $key): string
    {
        return Crypt::decrypt((string) Setting::get($key, ''));
    }

    /** Ganti placeholder dengan data peserta. */
    public static function render(string $template, array $vars): string
    {
        $out = strtr($template, $vars);
        // Rapikan baris yang placeholdernya kosong, mis. "Grup WhatsApp: " tanpa link.
        $out = preg_replace('/^[^\S\n]*[^\n:]{1,40}:[^\S\n]*(?:\n|$)/mu', '', $out) ?? $out;
        $out = preg_replace("/\n{3,}/", "\n\n", $out) ?? $out;
        return trim($out);
    }

    /** @param array<string,mixed> $reg @param array<string,mixed> $event */
    public static function vars(array $reg, array $event): array
    {
        $date = '';
        if (!empty($event['starts_at'])) {
            $date = day_id((string) $event['starts_at']) . ', ' . date_id((string) $event['starts_at']) . ' ' . date('T');
        }
        return [
            '{nama}'       => (string) $reg['name'],
            '{event}'      => (string) $event['title'],
            '{tanggal}'    => $date,
            '{lokasi}'     => (string) ($event['location'] ?? ''),
            '{kode}'       => (string) $reg['code'],
            '{link_tiket}' => full_url('t/' . $reg['code']),
            '{link_grup}'  => safe_url((string) ($event['group_link'] ?? '')),
            '{instansi}'   => (string) ($reg['representative'] ?? ''),
        ];
    }

    /**
     * Masukkan notifikasi ke antrean. @return int[] id notifikasi
     */
    public static function queueForRegistration(array $reg, array $event): array
    {
        if (!self::enabled()) {
            return [];
        }
        $vars = self::vars($reg, $event);
        $ids = [];
        $provider = (string) Setting::get('notify_wa_provider', 'none');
        if (isset(self::WA_PROVIDERS[$provider]) && $provider !== 'none' && valid_wa((string) $reg['wa'])) {
            $ids[] = Notification::create([
                'registration_id' => (int) $reg['id'], 'channel' => 'whatsapp', 'provider' => $provider,
                'recipient' => (string) $reg['wa'],
                'message' => self::render((string) Setting::get('notify_wa_template', self::DEFAULT_WA_TEMPLATE), $vars),
            ]);
        }
        if (Setting::get('notify_email_enabled', '0') === '1' && !empty($reg['email']) && filter_var($reg['email'], FILTER_VALIDATE_EMAIL)) {
            $ids[] = Notification::create([
                'registration_id' => (int) $reg['id'], 'channel' => 'email', 'provider' => (string) Setting::get('notify_email_driver', 'smtp'),
                'recipient' => (string) $reg['email'],
                'subject' => mb_substr(self::render((string) Setting::get('notify_email_subject', self::DEFAULT_EMAIL_SUBJECT), $vars), 0, 200),
                'message' => self::render((string) Setting::get('notify_email_template', self::DEFAULT_EMAIL_TEMPLATE), $vars),
            ]);
        }
        $hook = safe_url((string) Setting::get('notify_webhook_url', ''));
        if (Setting::get('notify_webhook_enabled', '0') === '1' && $hook !== '') {
            $payload = [
                'event' => 'registration.created',
                'sent_at' => date('c'),
                'registration' => [
                    'code' => $reg['code'], 'name' => $reg['name'], 'wa' => $reg['wa'], 'email' => $reg['email'] ?? null,
                    'representative' => $reg['representative'] ?? null, 'address' => $reg['address'] ?? null,
                    'extra' => json_decode((string) ($reg['extra'] ?? ''), true) ?: new \stdClass(),
                    'ticket_url' => $vars['{link_tiket}'], 'registered_at' => $reg['created_at'] ?? now(),
                ],
                'event_info' => [
                    'id' => (int) $event['id'], 'slug' => $event['slug'], 'title' => $event['title'],
                    'starts_at' => $event['starts_at'] ?? null, 'location' => $event['location'] ?? null,
                ],
            ];
            $ids[] = Notification::create([
                'registration_id' => (int) $reg['id'], 'channel' => 'webhook', 'provider' => 'webhook',
                'recipient' => mb_substr($hook, 0, 190),
                'message' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
        return $ids;
    }

    /** Proses antrean (id tertentu dan/atau yang jatuh tempo). */
    public static function process(array $ids = [], int $dueLimit = 5): array
    {
        $ids = array_values(array_unique(array_merge(array_map('intval', $ids), $dueLimit > 0 ? Notification::dueIds($dueLimit) : [])));
        $result = ['sent' => 0, 'failed' => 0];
        foreach ($ids as $id) {
            if (!Notification::claim($id)) {
                continue;
            }
            $row = Notification::find($id);
            if (!$row) {
                continue;
            }
            try {
                self::deliver($row);
                Notification::markSent($id);
                $result['sent']++;
            } catch (\Throwable $e) {
                Notification::markFailed($id, (int) $row['attempts'], $e->getMessage());
                $result['failed']++;
            }
        }
        return $result;
    }

    /** Kirim satu notifikasi; lempar exception bila gagal. */
    public static function deliver(array $row): void
    {
        switch ($row['channel']) {
            case 'whatsapp':
                self::sendWhatsApp((string) $row['provider'], (string) $row['recipient'], (string) $row['message']);
                return;
            case 'email':
                self::sendEmail((string) $row['recipient'], (string) $row['subject'], (string) $row['message']);
                return;
            case 'webhook':
                self::sendWebhook((string) $row['recipient'], (string) $row['message']);
                return;
        }
        throw new \RuntimeException('Kanal tidak dikenal: ' . $row['channel']);
    }

    /** ⚠️ Memanggil API WA gateway pihak ketiga. */
    public static function sendWhatsApp(string $provider, string $to, string $message): void
    {
        $token = self::secret('notify_wa_token');
        if ($token === '') {
            throw new \RuntimeException('Token API WhatsApp belum diisi');
        }
        if ($provider === 'fonnte') {
            $res = HttpClient::post((string) config('app.fonnte_url'), [
                'target' => $to, 'message' => $message, 'countryCode' => (string) Setting::get('wa_country_code', '62'),
            ], ['Authorization' => $token]);
        } elseif ($provider === 'wablas') {
            $domain = rtrim(safe_url((string) Setting::get('notify_wablas_domain', '')), '/');
            if ($domain === '') {
                throw new \RuntimeException('Domain server Wablas belum diisi');
            }
            $res = HttpClient::post($domain . '/api/send-message', ['phone' => $to, 'message' => $message], ['Authorization' => $token]);
        } else {
            throw new \RuntimeException('Provider WhatsApp tidak aktif');
        }
        self::assertApiOk($res);
    }

    /** @param array{status:int,body:string,error:string} $res */
    private static function assertApiOk(array $res): void
    {
        if ($res['error'] !== '') {
            throw new \RuntimeException('Koneksi gagal: ' . $res['error']);
        }
        $json = json_decode($res['body'], true);
        $ok = $res['status'] >= 200 && $res['status'] < 300 && (!is_array($json) || !array_key_exists('status', $json) || $json['status'] === true || $json['status'] === 'true');
        if (!$ok) {
            $reason = is_array($json) ? (string) ($json['reason'] ?? $json['message'] ?? $json['detail'] ?? '') : '';
            throw new \RuntimeException('API menolak (HTTP ' . $res['status'] . ')' . ($reason !== '' ? ': ' . mb_substr($reason, 0, 150) : ''));
        }
    }

    public static function mailer(): Mailer
    {
        return new Mailer([
            'driver'     => (string) Setting::get('notify_email_driver', 'smtp'),
            'host'       => (string) Setting::get('notify_smtp_host', ''),
            'port'       => (int) Setting::get('notify_smtp_port', '587'),
            'encryption' => (string) Setting::get('notify_smtp_encryption', 'tls'),
            'username'   => (string) Setting::get('notify_smtp_username', ''),
            'password'   => self::secret('notify_smtp_password'),
            'from'       => (string) Setting::get('notify_mail_from', ''),
            'from_name'  => (string) Setting::get('notify_mail_from_name', '') ?: app_name(),
        ]);
    }

    /** ⚠️ Mengirim email lewat server SMTP yang dikonfigurasi. */
    public static function sendEmail(string $to, string $subject, string $text): void
    {
        $html = '<!doctype html><html><body style="margin:0;background:#f5f3ff;font-family:Segoe UI,Arial,sans-serif;color:#1e1b3a">'
            . '<div style="max-width:560px;margin:0 auto;padding:24px">'
            . '<div style="background:linear-gradient(135deg,#7c3aed,#c026d3,#f472b6);border-radius:18px 18px 0 0;padding:22px 24px;color:#fff;font-size:18px;font-weight:700">'
            . e(app_name()) . '</div><div style="background:#fff;border-radius:0 0 18px 18px;padding:24px;line-height:1.6;font-size:15px">'
            . preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#6d28d9">$1</a>', nl2br(e($text)))
            . '</div><p style="text-align:center;color:#7c8198;font-size:12px">Email otomatis — mohon tidak membalas.</p></div></body></html>';
        self::mailer()->send($to, $subject, $text, $html);
    }

    /** ⚠️ POST JSON ke URL webhook pihak ketiga, ditandatangani HMAC-SHA256. */
    public static function sendWebhook(string $url, string $json): void
    {
        $url = safe_url($url);
        if ($url === '') {
            throw new \RuntimeException('URL webhook tidak valid');
        }
        $secret = self::secret('notify_webhook_secret');
        $headers = ['Content-Type' => 'application/json', 'X-Presensi-Event' => 'registration.created'];
        if ($secret !== '') {
            $headers['X-Presensi-Signature'] = 'sha256=' . hash_hmac('sha256', $json, $secret);
        }
        $res = HttpClient::post($url, $json, $headers);
        if ($res['error'] !== '') {
            throw new \RuntimeException('Koneksi gagal: ' . $res['error']);
        }
        if ($res['status'] < 200 || $res['status'] >= 300) {
            throw new \RuntimeException('Webhook membalas HTTP ' . $res['status']);
        }
    }

    /** Contoh data untuk kirim tes & pratinjau. */
    public static function sampleVars(): array
    {
        return [
            '{nama}' => 'Budi Santoso', '{event}' => 'Seminar Contoh', '{tanggal}' => 'Sabtu, 20 Desember 2026, 09:00 ' . date('T'),
            '{lokasi}' => 'Aula Utama', '{kode}' => 'AB12CD34', '{link_tiket}' => full_url('t/AB12CD34'),
            '{link_grup}' => 'https://chat.whatsapp.com/contoh', '{instansi}' => 'PT Contoh',
        ];
    }
}
