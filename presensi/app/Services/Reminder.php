<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\Notification;
use App\Models\Setting;

/**
 * Pengingat H-1 otomatis ke peserta.
 *
 * ⚠️ INTEGRASI EKSTERNAL: pengingat dikirim lewat kanal notifikasi yang sama
 * (WA gateway / SMTP), jadi membawa data peserta ke layanan pihak ketiga.
 *
 * Aturan:
 *  - Dikirim sehari sebelum acara pada jam yang diatur (default 09:00).
 *  - Hanya untuk peserta yang mendaftar LEBIH DARI 1 HARI (24 jam) sebelum acara
 *    dimulai, dan sudah terdaftar sebelum jam pengingat.
 *  - Satu kali per peserta (kolom registrations.reminded_at).
 *  - Tidak dikirim untuk event draft, event yang menonaktifkan pengingat,
 *    atau bila acara dimulai kurang dari 1 jam lagi.
 *  - Pesan yang belum terkirim saat acara dimulai otomatis kedaluwarsa.
 */
final class Reminder
{
    public const DEFAULT_TIME = '09:00';

    public const DEFAULT_WA_TEMPLATE = "{Halo|Hai} {nama} 👋\n\n{Mengingatkan|Sekadar mengingatkan}, *besok* Anda terdaftar di *{event}*.\n🗓 {tanggal}\n📍 {lokasi}\n🎫 Kode tiket: *{kode}*\n\nTiket & QR code: {link_tiket}\n\n{Sampai jumpa besok!|Sampai bertemu besok!}";
    public const DEFAULT_EMAIL_SUBJECT = 'Pengingat: {event} besok';
    public const DEFAULT_EMAIL_TEMPLATE = "Halo {nama},\n\nMengingatkan, besok Anda terdaftar di {event}.\n\nJadwal : {tanggal}\nLokasi : {lokasi}\nKode tiket : {kode}\n\nBuka tiket & QR code Anda di: {link_tiket}\n\nSampai jumpa besok!";

    public static function enabled(): bool
    {
        return Notifier::enabled() && Setting::get('notify_reminder_enabled', '0') === '1';
    }

    public static function sendTime(): string
    {
        $t = (string) Setting::get('notify_reminder_time', self::DEFAULT_TIME);
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ? $t : self::DEFAULT_TIME;
    }

    /** Waktu kirim pengingat untuk sebuah acara: H-1 pada jam $time. */
    public static function reminderAt(string $startsAt, ?string $time = null): int
    {
        $start = (int) strtotime($startsAt);
        return (int) strtotime(date('Y-m-d', $start - 86400) . ' ' . ($time ?? self::sendTime()) . ':00');
    }

    /** Apakah peserta berhak diingatkan (daftar > 1 hari sebelum acara & sebelum jam pengingat)? */
    public static function eligible(string $createdAt, string $startsAt, ?string $time = null): bool
    {
        $created = (int) strtotime($createdAt);
        $start = (int) strtotime($startsAt);
        return $start - $created > 86400 && $created < self::reminderAt($startsAt, $time);
    }

    /**
     * Antrekan pengingat yang sudah jatuh tempo. Dipanggil dari cron & tick (maks. 1x/menit).
     * @return int jumlah peserta yang diantrekan
     */
    public static function dispatchDue(?int $now = null, bool $force = false): int
    {
        if (!self::enabled()) {
            return 0;
        }
        $now = $now ?? time();
        if (!$force && $now - (int) Setting::fresh('reminder_last_scan', '0') < 60) {
            return 0;
        }
        Setting::set('reminder_last_scan', (string) $now);

        $time = self::sendTime();
        $rows = DB::select(
            "SELECT r.*, e.id AS e_id, e.slug AS e_slug, e.title AS e_title, e.starts_at AS e_starts_at,
                    e.location AS e_location, e.group_link AS e_group_link
             FROM registrations r JOIN events e ON e.id = r.event_id
             WHERE r.reminded_at IS NULL AND e.status <> 'draft' AND e.send_reminder = 1
               AND e.starts_at IS NOT NULL AND e.starts_at > ? AND e.starts_at <= ?
               AND r.created_at < DATE_SUB(e.starts_at, INTERVAL 1 DAY)
             ORDER BY e.starts_at ASC, r.id ASC LIMIT 1000",
            [date('Y-m-d H:i:s', $now + 3600), date('Y-m-d H:i:s', $now + 2 * 86400)]
        );

        $provider = (string) Setting::get('notify_wa_provider', 'none');
        $waOn = $provider !== 'none' && isset(Notifier::WA_PROVIDERS[$provider]);
        $mailOn = Setting::get('notify_email_enabled', '0') === '1';
        $queued = 0;

        foreach ($rows as $r) {
            $startsAt = (string) $r['e_starts_at'];
            if ($now < self::reminderAt($startsAt, $time) || !self::eligible((string) $r['created_at'], $startsAt, $time)) {
                continue;
            }
            // Tandai dulu agar proses paralel tidak mengantrekan dua kali.
            $claimed = DB::run('UPDATE registrations SET reminded_at = ? WHERE id = ? AND reminded_at IS NULL', [date('Y-m-d H:i:s', $now), (int) $r['id']])->rowCount();
            if ($claimed !== 1) {
                continue;
            }
            $event = ['id' => $r['e_id'], 'slug' => $r['e_slug'], 'title' => $r['e_title'], 'starts_at' => $startsAt,
                      'location' => $r['e_location'], 'group_link' => $r['e_group_link']];
            $vars = Notifier::vars($r, $event);
            $expires = $startsAt; // tidak ada gunanya mengingatkan setelah acara dimulai
            if ($waOn && valid_wa((string) $r['wa'])) {
                Notification::create([
                    'registration_id' => (int) $r['id'], 'kind' => 'reminder', 'channel' => 'whatsapp', 'provider' => $provider,
                    'recipient' => (string) $r['wa'], 'expires_at' => $expires,
                    'message' => WaThrottle::spin(Notifier::render((string) Setting::get('notify_reminder_wa_template', self::DEFAULT_WA_TEMPLATE), $vars)),
                ]);
            }
            if ($mailOn && !empty($r['email']) && filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
                Notification::create([
                    'registration_id' => (int) $r['id'], 'kind' => 'reminder', 'channel' => 'email',
                    'provider' => (string) Setting::get('notify_email_driver', 'smtp'), 'recipient' => (string) $r['email'], 'expires_at' => $expires,
                    'subject' => mb_substr(Notifier::render((string) Setting::get('notify_reminder_email_subject', self::DEFAULT_EMAIL_SUBJECT), $vars), 0, 200),
                    'message' => Notifier::render((string) Setting::get('notify_reminder_email_template', self::DEFAULT_EMAIL_TEMPLATE), $vars),
                ]);
            }
            $queued++;
        }
        return $queued;
    }

    /** Ringkasan pengingat untuk event 7 hari ke depan (ditampilkan di panel admin). */
    public static function upcoming(): array
    {
        $time = self::sendTime();
        $events = DB::select(
            "SELECT id, title, starts_at, send_reminder FROM events
             WHERE status <> 'draft' AND starts_at IS NOT NULL AND starts_at > ? AND starts_at <= ?
             ORDER BY starts_at ASC LIMIT 20",
            [now(), date('Y-m-d H:i:s', time() + 8 * 86400)]
        );
        $out = [];
        foreach ($events as $e) {
            $regs = DB::select('SELECT created_at, reminded_at FROM registrations WHERE event_id = ?', [(int) $e['id']]);
            $eligible = 0;
            $done = 0;
            foreach ($regs as $r) {
                if ($r['reminded_at']) {
                    $done++;
                } elseif (self::eligible((string) $r['created_at'], (string) $e['starts_at'], $time)) {
                    $eligible++;
                }
            }
            $at = self::reminderAt((string) $e['starts_at'], $time);
            $eta = WaThrottle::estimateSeconds($eligible);
            $out[] = [
                'event' => $e, 'remind_at' => $at, 'eligible' => $eligible, 'done' => $done, 'total' => count($regs),
                'eta' => $eta, 'late' => $eligible > 0 && $at + $eta > (int) strtotime((string) $e['starts_at']) - 3600,
            ];
        }
        return $out;
    }

    /** Status pengingat satu peserta (untuk halaman detail). */
    public static function statusFor(array $reg, ?string $startsAt, bool $eventEnabled): string
    {
        if (!$startsAt) {
            return 'Tidak berlaku (jadwal event belum diisi)';
        }
        if (!empty($reg['reminded_at'])) {
            return 'Diantrekan ' . date_id((string) $reg['reminded_at']);
        }
        if (!self::enabled() || !$eventEnabled) {
            return 'Nonaktif';
        }
        if (!self::eligible((string) $reg['created_at'], $startsAt)) {
            return 'Tidak berlaku (mendaftar kurang dari 1 hari sebelum acara)';
        }
        $at = self::reminderAt($startsAt);
        return $at > time() ? 'Dijadwalkan ' . date_id(date('Y-m-d H:i:s', $at)) : 'Menunggu diproses';
    }
}
