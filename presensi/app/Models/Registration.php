<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Paginator;

final class Registration
{
    public static function find(int $id): ?array
    {
        return DB::first(
            'SELECT r.*, e.title AS event_title, e.slug AS event_slug, e.fields AS event_fields, e.representative_label
             FROM registrations r JOIN events e ON e.id = r.event_id WHERE r.id = ?',
            [$id]
        );
    }

    public static function findByCode(string $code): ?array
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if ($code === '') {
            return null;
        }
        return DB::first(
            'SELECT r.*, e.title AS event_title, e.slug AS event_slug, e.theme, e.starts_at, e.ends_at,
                    e.location, e.group_link, e.success_message, e.redirect_seconds, e.fields AS event_fields,
                    e.representative_label
             FROM registrations r JOIN events e ON e.id = r.event_id WHERE r.code = ?',
            [$code]
        );
    }

    public static function findDuplicate(int $eventId, string $wa): ?array
    {
        return DB::first('SELECT * FROM registrations WHERE event_id = ? AND wa = ? ORDER BY id ASC LIMIT 1', [$eventId, $wa]);
    }

    public static function countForEvent(int $eventId): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM registrations WHERE event_id = ?', [$eventId]);
    }

    public static function create(array $data): array
    {
        $now = now();
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        for ($i = 0; $i < 5; $i++) {
            $data['code'] = random_code(8);
            try {
                $id = DB::insert('registrations', $data);
                $data['id'] = $id;
                return $data;
            } catch (\PDOException $e) {
                // 23000 = duplicate key (kode kebetulan sama) -> coba lagi
                if ($e->getCode() !== '23000' || $i === 4) {
                    throw $e;
                }
            }
        }
        throw new \RuntimeException('Gagal membuat kode tiket');
    }

    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = now();
        DB::update('registrations', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        DB::delete('registrations', 'id = ?', [$id]);
    }

    /** @param int[] $ids */
    public static function deleteMany(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return 0;
        }
        return DB::delete('registrations', 'id IN (' . DB::placeholders($ids) . ')', $ids);
    }

    public static function checkIn(int $id, int $userId): void
    {
        DB::run(
            'UPDATE registrations SET checked_in_at = ?, checked_in_by = ?, updated_at = ? WHERE id = ? AND checked_in_at IS NULL',
            [now(), $userId ?: null, now(), $id]
        );
    }

    /** @param int[] $ids */
    public static function checkInMany(array $ids, int $userId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return 0;
        }
        return DB::run(
            'UPDATE registrations SET checked_in_at = ?, checked_in_by = ?, updated_at = ?
             WHERE checked_in_at IS NULL AND id IN (' . DB::placeholders($ids) . ')',
            array_merge([now(), $userId ?: null, now()], $ids)
        )->rowCount();
    }

    public static function undoCheckIn(int $id): void
    {
        DB::run('UPDATE registrations SET checked_in_at = NULL, checked_in_by = NULL, updated_at = ? WHERE id = ?', [now(), $id]);
    }

    /**
     * @param array{event_id?:int,q?:string,status?:string,from?:string,to?:string} $f
     * @return array{0:string,1:array}
     */
    private static function filterSql(array $f): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($f['event_id'])) {
            $where[] = 'r.event_id = ?';
            $params[] = (int) $f['event_id'];
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = DB::like($q);
            $digits = preg_replace('/\D+/', '', $q) ?? '';
            $clauses = ['r.name LIKE ?', 'r.code LIKE ?', 'r.representative LIKE ?', 'r.address LIKE ?', 'r.email LIKE ?'];
            array_push($params, $like, $like, $like, $like, $like);
            if (strlen($digits) >= 4) {
                $clauses[] = 'r.wa LIKE ?';
                $params[] = DB::like(ltrim($digits, '0'));
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }
        $status = (string) ($f['status'] ?? '');
        if ($status === 'in') {
            $where[] = 'r.checked_in_at IS NOT NULL';
        } elseif ($status === 'out') {
            $where[] = 'r.checked_in_at IS NULL';
        }
        if (!empty($f['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $f['from'])) {
            $where[] = 'r.created_at >= ?';
            $params[] = $f['from'] . ' 00:00:00';
        }
        if (!empty($f['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $f['to'])) {
            $where[] = 'r.created_at <= ?';
            $params[] = $f['to'] . ' 23:59:59';
        }
        return [implode(' AND ', $where), $params];
    }

    private const SORTS = [
        'newest' => 'r.id DESC',
        'oldest' => 'r.id ASC',
        'name'   => 'r.name ASC',
        'checkin' => 'r.checked_in_at IS NULL, r.checked_in_at DESC',
    ];

    public static function paginate(array $filters, int $page, int $perPage = 25, string $sort = 'newest'): Paginator
    {
        [$where, $params] = self::filterSql($filters);
        $total = (int) DB::value('SELECT COUNT(*) FROM registrations r WHERE ' . $where, $params);
        $order = self::SORTS[$sort] ?? self::SORTS['newest'];
        $offset = Paginator::offset(min($page, max(1, (int) ceil($total / max(1, $perPage)))), $perPage);
        $rows = DB::select(
            'SELECT r.*, e.title AS event_title, e.theme FROM registrations r
             JOIN events e ON e.id = r.event_id
             WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params
        );
        return new Paginator($rows, $total, $perPage, $page);
    }

    /** Iterasi hasil filter untuk export tanpa memuat semua ke memori. */
    public static function cursor(array $filters): \PDOStatement
    {
        [$where, $params] = self::filterSql($filters);
        $pdo = DB::pdo();
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        return DB::run(
            'SELECT r.*, e.title AS event_title FROM registrations r JOIN events e ON e.id = r.event_id
             WHERE ' . $where . ' ORDER BY r.id ASC',
            $params
        );
    }

    /** Pencarian cepat untuk halaman check-in. */
    public static function quickSearch(string $q, int $eventId = 0, int $limit = 15): array
    {
        $f = ['q' => $q, 'event_id' => $eventId];
        [$where, $params] = self::filterSql($f);
        return DB::select(
            'SELECT r.id, r.code, r.name, r.wa, r.representative, r.checked_in_at, e.title AS event_title
             FROM registrations r JOIN events e ON e.id = r.event_id WHERE ' . $where . '
             ORDER BY r.checked_in_at IS NULL DESC, r.name ASC LIMIT ' . (int) $limit,
            $params
        );
    }

    public static function stats(int $eventId = 0): array
    {
        $cond = $eventId ? ' WHERE event_id = ' . (int) $eventId : '';
        $today = date('Y-m-d 00:00:00');
        $row = DB::first(
            'SELECT COUNT(*) AS total,
                    SUM(checked_in_at IS NOT NULL) AS checked_in,
                    SUM(created_at >= ?) AS today
             FROM registrations' . $cond,
            [$today]
        ) ?? [];
        return [
            'total'      => (int) ($row['total'] ?? 0),
            'checked_in' => (int) ($row['checked_in'] ?? 0),
            'today'      => (int) ($row['today'] ?? 0),
        ];
    }

    /** Jumlah pendaftar per hari (N hari terakhir). @return array<string,int> */
    public static function daily(int $days = 14, int $eventId = 0): array
    {
        $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $params = [$start . ' 00:00:00'];
        $cond = '';
        if ($eventId) {
            $cond = ' AND event_id = ?';
            $params[] = $eventId;
        }
        $rows = DB::select(
            'SELECT DATE(created_at) d, COUNT(*) c FROM registrations WHERE created_at >= ?' . $cond . ' GROUP BY DATE(created_at)',
            $params
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['d']] = (int) $r['c'];
        }
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = date('Y-m-d', strtotime($start . ' +' . $i . ' days'));
            $out[$d] = $map[$d] ?? 0;
        }
        return $out;
    }

    public static function recent(int $limit = 8): array
    {
        return DB::select(
            'SELECT r.id, r.name, r.wa, r.representative, r.created_at, r.checked_in_at, e.title AS event_title, e.theme
             FROM registrations r JOIN events e ON e.id = r.event_id ORDER BY r.id DESC LIMIT ' . (int) $limit
        );
    }

    public static function topRepresentatives(int $eventId = 0, int $limit = 6): array
    {
        $params = [];
        $cond = "WHERE representative IS NOT NULL AND representative <> ''";
        if ($eventId) {
            $cond .= ' AND event_id = ?';
            $params[] = $eventId;
        }
        return DB::select(
            'SELECT representative, COUNT(*) c FROM registrations ' . $cond . ' GROUP BY representative ORDER BY c DESC LIMIT ' . (int) $limit,
            $params
        );
    }

    public static function extra(array $row): array
    {
        $x = json_decode((string) ($row['extra'] ?? ''), true);
        return is_array($x) ? $x : [];
    }
}
