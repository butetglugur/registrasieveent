<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Event
{
    public const STATUSES = [
        'open'   => 'Dibuka',
        'closed' => 'Ditutup',
        'draft'  => 'Draft',
    ];

    public const FIELD_TYPES = [
        'text'     => 'Teks singkat',
        'textarea' => 'Paragraf',
        'email'    => 'Email',
        'number'   => 'Angka',
        'date'     => 'Tanggal',
        'select'   => 'Dropdown',
        'radio'    => 'Pilihan ganda',
        'checkbox' => 'Kotak centang (boleh lebih dari satu)',
    ];

    /** Kolom bawaan yang tidak boleh dipakai sebagai key field tambahan. */
    private const RESERVED_KEYS = ['name', 'wa', 'email', 'address', 'representative', 'code', 'id', 'website', '_token', 'ts'];

    public static function find(int $id): ?array
    {
        return DB::first('SELECT * FROM events WHERE id = ?', [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return DB::first('SELECT * FROM events WHERE slug = ?', [$slug]);
    }

    /** Semua event beserta jumlah pendaftar & yang sudah hadir. */
    public static function allWithStats(string $status = '', string $search = ''): array
    {
        $where = ['1=1'];
        $params = [];
        if ($status !== '' && isset(self::STATUSES[$status])) {
            $where[] = 'e.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(e.title LIKE ? OR e.location LIKE ? OR e.slug LIKE ?)';
            $like = DB::like($search);
            array_push($params, $like, $like, $like);
        }
        return DB::select(
            'SELECT e.*,
                (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS total_reg,
                (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.checked_in_at IS NOT NULL) AS total_checkin
             FROM events e WHERE ' . implode(' AND ', $where) . '
             ORDER BY (e.status = \'open\') DESC, COALESCE(e.starts_at, e.created_at) DESC',
            $params
        );
    }

    public static function options(): array
    {
        return DB::select('SELECT id, title, status FROM events ORDER BY created_at DESC');
    }

    /** Event yang tampil di halaman depan. */
    public static function publicList(): array
    {
        return DB::select(
            "SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS total_reg
             FROM events e WHERE e.status IN ('open','closed')
             ORDER BY (e.status = 'open') DESC, COALESCE(e.starts_at, e.created_at) DESC LIMIT 30"
        );
    }

    public static function create(array $data, ?int $userId = null): int
    {
        $now = now();
        $data['created_by'] = $userId;
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        return DB::insert('events', $data);
    }

    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = now();
        DB::update('events', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        DB::delete('events', 'id = ?', [$id]);
    }

    public static function uniqueSlug(string $base, int $ignoreId = 0): string
    {
        $base = slugify($base) ?: 'event';
        $slug = $base;
        $i = 2;
        while ((int) DB::value('SELECT COUNT(*) FROM events WHERE slug = ? AND id <> ?', [$slug, $ignoreId]) > 0) {
            $slug = substr($base, 0, 70) . '-' . $i++;
        }
        return $slug;
    }

    public static function duplicate(int $id, ?int $userId): int
    {
        $event = self::find($id);
        if (!$event) {
            throw new \RuntimeException('Event tidak ditemukan');
        }
        unset($event['id'], $event['created_at'], $event['updated_at'], $event['created_by']);
        $event['title'] = mb_substr($event['title'] . ' (Salinan)', 0, 150);
        $event['slug'] = self::uniqueSlug($event['slug'] . '-salinan');
        $event['status'] = 'draft';
        return self::create($event, $userId);
    }

    /** @return array<int,array<string,mixed>> */
    public static function fields(array $event): array
    {
        $fields = json_decode((string) ($event['fields'] ?? ''), true);
        return is_array($fields) ? $fields : [];
    }

    /**
     * Bersihkan definisi field dari form builder (input tak tepercaya).
     * @return array<int,array<string,mixed>>
     */
    public static function sanitizeFields($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        $used = [];
        foreach (array_slice($raw, 0, 30) as $f) {
            if (!is_array($f)) {
                continue;
            }
            $label = trim(mb_substr(strip_tags((string) ($f['label'] ?? '')), 0, 100));
            if ($label === '') {
                continue;
            }
            $type = (string) ($f['type'] ?? 'text');
            if (!isset(self::FIELD_TYPES[$type])) {
                $type = 'text';
            }
            $key = slugify((string) ($f['key'] ?? ''), 40);
            if ($key === '') {
                $key = slugify($label, 40);
            }
            $key = str_replace('-', '_', $key ?: 'field');
            if (in_array($key, self::RESERVED_KEYS, true)) {
                $key = 'f_' . $key;
            }
            $baseKey = $key;
            $n = 2;
            while (isset($used[$key])) {
                $key = $baseKey . '_' . $n++;
            }
            $used[$key] = true;

            $field = [
                'key'         => $key,
                'label'       => $label,
                'type'        => $type,
                'required'    => !empty($f['required']),
                'placeholder' => trim(mb_substr(strip_tags((string) ($f['placeholder'] ?? '')), 0, 120)),
                'options'     => [],
            ];
            if (in_array($type, ['select', 'radio', 'checkbox'], true)) {
                $opts = $f['options'] ?? [];
                if (is_string($opts)) {
                    $opts = preg_split('/\r\n|\r|\n|,/', $opts) ?: [];
                }
                $clean = [];
                foreach ((array) $opts as $o) {
                    $o = trim(mb_substr(strip_tags((string) $o), 0, 100));
                    if ($o !== '' && !in_array($o, $clean, true)) {
                        $clean[] = $o;
                    }
                }
                $field['options'] = array_slice($clean, 0, 50);
                if (!$field['options']) {
                    continue; // pilihan tanpa opsi tidak berguna
                }
            }
            $out[] = $field;
        }
        return $out;
    }

    /**
     * Apakah pendaftaran dibuka? @return array{0:bool,1:string}
     */
    public static function registrationState(array $event, ?int $count = null): array
    {
        if ($event['status'] === 'draft') {
            return [false, 'Event ini belum dipublikasikan.'];
        }
        if ($event['status'] === 'closed') {
            return [false, 'Pendaftaran untuk event ini sudah ditutup.'];
        }
        if (!empty($event['closes_at']) && strtotime((string) $event['closes_at']) <= time()) {
            return [false, 'Batas waktu pendaftaran sudah berakhir.'];
        }
        if (!empty($event['quota'])) {
            $count = $count ?? Registration::countForEvent((int) $event['id']);
            if ($count >= (int) $event['quota']) {
                return [false, 'Kuota peserta sudah penuh. Terima kasih atas antusiasme Anda!'];
            }
        }
        return [true, ''];
    }

    public static function representativeLabel(array $event): string
    {
        $l = trim((string) ($event['representative_label'] ?? ''));
        return $l !== '' ? $l : 'Instansi / Perwakilan';
    }

    public static function countByStatus(): array
    {
        $out = ['open' => 0, 'closed' => 0, 'draft' => 0];
        foreach (DB::select('SELECT status, COUNT(*) c FROM events GROUP BY status') as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }
}
