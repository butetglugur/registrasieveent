<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Paginator;

final class ActivityLog
{
    public static function record(string $action, string $description, ?int $userId = null): void
    {
        try {
            DB::insert('activity_logs', [
                'user_id'     => $userId === null ? (Auth::id() ?: null) : ($userId ?: null),
                'action'      => mb_substr($action, 0, 50),
                'description' => mb_substr($description, 0, 255),
                'ip'          => client_ip(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Log aktivitas tidak boleh menggagalkan aksi utama.
        }
    }

    public static function paginate(int $page, int $perPage = 30, string $action = ''): Paginator
    {
        $where = '1=1';
        $params = [];
        if ($action !== '') {
            $where .= ' AND l.action = ?';
            $params[] = $action;
        }
        $total = (int) DB::value('SELECT COUNT(*) FROM activity_logs l WHERE ' . $where, $params);
        $offset = Paginator::offset($page, $perPage);
        $rows = DB::select(
            'SELECT l.*, u.name AS user_name, u.username FROM activity_logs l
             LEFT JOIN users u ON u.id = l.user_id WHERE ' . $where . '
             ORDER BY l.id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params
        );
        return new Paginator($rows, $total, $perPage, $page);
    }

    public static function actions(): array
    {
        return array_column(DB::select('SELECT DISTINCT action FROM activity_logs ORDER BY action'), 'action');
    }

    public static function recent(int $limit = 8): array
    {
        return DB::select(
            'SELECT l.*, u.name AS user_name FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.id DESC LIMIT ' . (int) $limit
        );
    }

    public static function prune(int $days = 180): void
    {
        DB::run('DELETE FROM activity_logs WHERE created_at < ?', [date('Y-m-d H:i:s', time() - $days * 86400)]);
    }
}
