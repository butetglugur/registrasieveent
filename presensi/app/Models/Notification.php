<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Paginator;

final class Notification
{
    public const STATUSES = ['pending' => 'Antre', 'sending' => 'Mengirim', 'sent' => 'Terkirim', 'failed' => 'Gagal', 'cancelled' => 'Dibatalkan'];
    public const MAX_ATTEMPTS = 3;

    public static function create(array $data): int
    {
        $data['created_at'] = now();
        $data['next_attempt_at'] = $data['next_attempt_at'] ?? now();
        return DB::insert('notifications', $data);
    }

    public static function find(int $id): ?array
    {
        return DB::first('SELECT * FROM notifications WHERE id = ?', [$id]);
    }

    /** Klaim baris agar tidak terkirim ganda oleh proses paralel. */
    public static function claim(int $id): bool
    {
        return DB::run(
            "UPDATE notifications SET status = 'sending', attempts = attempts + 1 WHERE id = ? AND status = 'pending'",
            [$id]
        )->rowCount() === 1;
    }

    public static function markSent(int $id): void
    {
        DB::run("UPDATE notifications SET status = 'sent', sent_at = ?, last_error = NULL WHERE id = ?", [now(), $id]);
    }

    public static function markFailed(int $id, int $attempts, string $error): void
    {
        $final = $attempts >= self::MAX_ATTEMPTS;
        $delay = [1 => 60, 2 => 300][$attempts] ?? 1800; // backoff: 1 menit, 5 menit
        DB::run(
            'UPDATE notifications SET status = ?, last_error = ?, next_attempt_at = ? WHERE id = ?',
            [$final ? 'failed' : 'pending', mb_substr($error, 0, 255), date('Y-m-d H:i:s', time() + $delay), $id]
        );
    }

    /** @return int[] */
    public static function dueIds(int $limit): array
    {
        // Pulihkan baris yang macet di status "sending" (mis. proses mati di tengah jalan)
        DB::run("UPDATE notifications SET status = 'pending' WHERE status = 'sending' AND next_attempt_at < ?", [date('Y-m-d H:i:s', time() - 600)]);
        return array_map('intval', array_column(DB::select(
            "SELECT id FROM notifications WHERE status = 'pending' AND next_attempt_at <= ? ORDER BY id ASC LIMIT " . (int) $limit,
            [now()]
        ), 'id'));
    }

    /** Tunda semua WA yang antre hingga waktu tertentu (dipakai gerbang anti-blokir). */
    /** Batalkan pesan yang lewat masa berlakunya (mis. pengingat setelah acara dimulai). */
    public static function expireOverdue(): int
    {
        return DB::run(
            "UPDATE notifications SET status = 'cancelled', last_error = 'Kedaluwarsa: acara sudah dimulai'
             WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at <= ?",
            [now()]
        )->rowCount();
    }

    public static function postponeWhatsApp(int $untilTs): void
    {
        $until = date('Y-m-d H:i:s', $untilTs);
        DB::run("UPDATE notifications SET next_attempt_at = ? WHERE channel = 'whatsapp' AND status = 'pending' AND next_attempt_at < ?", [$until, $until]);
    }

    public static function cancelPendingWhatsApp(): int
    {
        return DB::run("UPDATE notifications SET status = 'cancelled', last_error = 'Dibatalkan admin' WHERE channel = 'whatsapp' AND status = 'pending'")->rowCount();
    }

    public static function pendingCount(string $channel = ''): int
    {
        if ($channel !== '') {
            return (int) DB::value("SELECT COUNT(*) FROM notifications WHERE status IN ('pending','sending') AND channel = ?", [$channel]);
        }
        return (int) DB::value("SELECT COUNT(*) FROM notifications WHERE status IN ('pending','sending')");
    }

    public static function nextDueAt(): ?string
    {
        $v = DB::value("SELECT MIN(next_attempt_at) FROM notifications WHERE status = 'pending'");
        return $v ? (string) $v : null;
    }

    public static function retry(int $id): void
    {
        DB::run("UPDATE notifications SET status = 'pending', attempts = 0, next_attempt_at = ? WHERE id = ? AND status IN ('failed','pending')", [now(), $id]);
    }

    public static function paginate(int $page, string $status = '', int $perPage = 20): Paginator
    {
        $where = '1=1';
        $params = [];
        if (isset(self::STATUSES[$status])) {
            $where = 'n.status = ?';
            $params[] = $status;
        }
        $total = (int) DB::value('SELECT COUNT(*) FROM notifications n WHERE ' . $where, $params);
        $rows = DB::select(
            'SELECT n.*, r.name AS reg_name, r.code AS reg_code FROM notifications n
             LEFT JOIN registrations r ON r.id = n.registration_id WHERE ' . $where . '
             ORDER BY n.id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . Paginator::offset($page, $perPage),
            $params
        );
        return new Paginator($rows, $total, $perPage, $page);
    }

    public static function counts(): array
    {
        $out = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach (DB::select('SELECT status, COUNT(*) c FROM notifications GROUP BY status') as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }
}
