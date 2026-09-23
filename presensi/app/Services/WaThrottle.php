<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\Setting;

/**
 * Pengatur laju (throttle) pengiriman WhatsApp untuk MENGURANGI risiko nomor diblokir.
 *
 * Aturan yang ditegakkan setiap kali akan mengirim 1 pesan WA:
 *  1. Jeda acak antarpesan (mis. 20–45 detik) — pola tidak seragam seperti bot.
 *  2. Istirahat setiap N pesan (mis. 5 menit tiap 15 pesan).
 *  3. Batas per jam & per hari.
 *  4. Jam tenang (tidak mengirim di malam hari).
 * Pesan yang belum boleh dikirim tetap di antrean dan dikirim otomatis nanti.
 */
final class WaThrottle
{
    public const DEFAULTS = [
        'notify_wa_delay_min'    => '20',
        'notify_wa_delay_max'    => '45',
        'notify_wa_batch_size'   => '15',
        'notify_wa_batch_rest'   => '5',
        'notify_wa_hourly_limit' => '60',
        'notify_wa_daily_limit'  => '300',
        'notify_quiet_enabled'   => '0',
        'notify_quiet_start'     => '21:00',
        'notify_quiet_end'       => '07:00',
    ];

    private const LOCK = 'presensi_wa_send';

    public static function cfg(string $key): string
    {
        return (string) Setting::get($key, self::DEFAULTS[$key] ?? '');
    }

    public static function int(string $key): int
    {
        return (int) self::cfg($key);
    }

    /**
     * Apakah boleh mengirim WA sekarang?
     * @return array{0:bool,1:int,2:string} [boleh, waktu_boleh_berikutnya, alasan]
     */
    public static function check(?int $now = null): array
    {
        $now = $now ?? time();
        $quietEnd = self::quietUntil($now);
        if ($quietEnd !== null) {
            return [false, $quietEnd, 'jam tenang'];
        }
        $next = (int) Setting::fresh('wa_next_allowed_at', '0');
        if ($now < $next) {
            return [false, $next, 'jeda antarpesan'];
        }
        $hourly = max(1, self::int('notify_wa_hourly_limit'));
        $since = date('Y-m-d H:i:s', $now - 3600);
        $sentHour = (int) DB::value("SELECT COUNT(*) FROM notifications WHERE channel = 'whatsapp' AND status = 'sent' AND sent_at >= ?", [$since]);
        if ($sentHour >= $hourly) {
            $oldest = (string) DB::value(
                "SELECT sent_at FROM notifications WHERE channel = 'whatsapp' AND status = 'sent' AND sent_at >= ? ORDER BY sent_at ASC LIMIT 1 OFFSET " . ($sentHour - $hourly),
                [$since]
            );
            return [false, max($now + 60, (int) strtotime($oldest) + 3600), 'batas per jam'];
        }
        $daily = max(1, self::int('notify_wa_daily_limit'));
        $sentDay = (int) DB::value("SELECT COUNT(*) FROM notifications WHERE channel = 'whatsapp' AND status = 'sent' AND sent_at >= ?", [date('Y-m-d 00:00:00', $now)]);
        if ($sentDay >= $daily) {
            $tomorrow = (int) strtotime(date('Y-m-d 00:00:00', $now) . ' +1 day');
            return [false, self::quietUntil($tomorrow) ?? $tomorrow, 'batas per hari'];
        }
        return [true, $now, ''];
    }

    /** Catat pengiriman: tentukan kapan pesan berikutnya boleh dikirim (jeda acak + istirahat). */
    public static function afterSend(?int $now = null): int
    {
        $now = $now ?? time();
        $min = max(1, self::int('notify_wa_delay_min'));
        $max = max($min, self::int('notify_wa_delay_max'));
        $delay = random_int($min, $max);
        $count = (int) Setting::fresh('wa_batch_count', '0') + 1;
        $batch = max(1, self::int('notify_wa_batch_size'));
        if ($count >= $batch) {
            $delay += max(0, self::int('notify_wa_batch_rest')) * 60;
            $count = 0;
        }
        Setting::set('wa_batch_count', (string) $count);
        Setting::set('wa_next_allowed_at', (string) ($now + $delay));
        return $now + $delay;
    }

    /** Bila $ts berada di jam tenang, kembalikan waktu berakhirnya; selain itu null. */
    public static function quietUntil(int $ts): ?int
    {
        if (self::cfg('notify_quiet_enabled') !== '1') {
            return null;
        }
        $start = self::minutes(self::cfg('notify_quiet_start'));
        $end = self::minutes(self::cfg('notify_quiet_end'));
        if ($start === null || $end === null || $start === $end) {
            return null;
        }
        $cur = (int) date('G', $ts) * 60 + (int) date('i', $ts);
        $inQuiet = $start < $end ? ($cur >= $start && $cur < $end) : ($cur >= $start || $cur < $end);
        if (!$inQuiet) {
            return null;
        }
        $endTs = (int) strtotime(date('Y-m-d', $ts) . sprintf(' %02d:%02d:00', intdiv($end, 60), $end % 60));
        if ($endTs <= $ts) {
            $endTs = (int) strtotime(date('Y-m-d', $ts) . sprintf(' %02d:%02d:00', intdiv($end, 60), $end % 60) . ' +1 day');
        }
        return $endTs;
    }

    private static function minutes(string $hhmm): ?int
    {
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $hhmm, $m)) {
            return null;
        }
        return (int) $m[1] * 60 + (int) $m[2];
    }

    /** Kunci agar dua proses tidak mengirim WA bersamaan (menjaga jeda tetap akurat). */
    public static function lock(): bool
    {
        return (int) DB::value('SELECT GET_LOCK(?, 0)', [self::LOCK]) === 1;
    }

    public static function unlock(): void
    {
        DB::value('SELECT RELEASE_LOCK(?)', [self::LOCK]);
    }

    /** Variasi kalimat (spintax): "{Halo|Hai|Selamat datang}" -> salah satu acak. */
    public static function spin(string $text): string
    {
        for ($i = 0; $i < 10; $i++) {
            $new = preg_replace_callback('/\{([^{}]*\|[^{}]*)\}/u', static function (array $m) {
                $opts = explode('|', $m[1]);
                return $opts[random_int(0, count($opts) - 1)];
            }, $text);
            if ($new === null || $new === $text) {
                break;
            }
            $text = $new;
        }
        return $text;
    }

    /** Perkiraan lama pengiriman N pesan (detik) sesuai aturan anti-blokir. */
    public static function estimateSeconds(int $n): int
    {
        if ($n <= 0) {
            return 0;
        }
        $avg = (self::int('notify_wa_delay_min') + max(self::int('notify_wa_delay_min'), self::int('notify_wa_delay_max'))) / 2;
        $rests = intdiv(max(0, $n - 1), max(1, self::int('notify_wa_batch_size'))) * self::int('notify_wa_batch_rest') * 60;
        $total = (int) (($n - 1) * $avg + $rests);
        $hourly = max(1, self::int('notify_wa_hourly_limit'));
        $total = max($total, (int) (ceil($n / $hourly) - 1) * 3600);
        $daily = max(1, self::int('notify_wa_daily_limit'));
        if ($n > $daily) {
            $total = max($total, (int) (ceil($n / $daily) - 1) * 86400);
        }
        return $total;
    }

    public static function humanDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return 'kurang dari 1 menit';
        }
        $d = intdiv($seconds, 86400);
        $h = intdiv($seconds % 86400, 3600);
        $m = intdiv($seconds % 3600, 60);
        $parts = [];
        if ($d) { $parts[] = $d . ' hari'; }
        if ($h) { $parts[] = $h . ' jam'; }
        if ($m && !$d) { $parts[] = $m . ' menit'; }
        return '± ' . implode(' ', $parts);
    }
}
