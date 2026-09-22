<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Registration;

final class CheckinController extends Controller
{
    public function index(Request $request): string
    {
        $eventId = $request->int('event');
        $event = $eventId ? Event::find($eventId) : null;
        return view('admin.checkin.index', [
            'events' => Event::options(),
            'event'  => $event,
            'stats'  => Registration::stats($event ? (int) $event['id'] : 0),
        ]);
    }

    /** Ambil kode tiket dari isi QR (bisa berupa URL tiket atau kode saja). */
    public static function extractCode(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('#/t/([A-Za-z0-9]{4,16})(?:[/?\#]|$)#', $raw, $m)) {
            return strtoupper($m[1]);
        }
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
    }

    public function store(Request $request): Response
    {
        $code = self::extractCode(mb_substr($request->str('code'), 0, 300));
        $eventId = $request->int('event_id');
        if ($code === '' || strlen($code) > 16) {
            return Response::json(['ok' => false, 'status' => 'invalid', 'message' => 'Kode tiket tidak valid.'], 422);
        }
        $reg = Registration::findByCode($code);
        if (!$reg) {
            return Response::json(['ok' => false, 'status' => 'not_found', 'message' => 'Tiket ' . $code . ' tidak ditemukan.'], 404);
        }
        $payload = [
            'id'    => (int) $reg['id'],
            'code'  => $reg['code'],
            'name'  => $reg['name'],
            'representative' => $reg['representative'],
            'event' => $reg['event_title'],
            'url'   => route('admin.registrations.show', ['id' => $reg['id']]),
        ];
        if ($eventId && (int) $reg['event_id'] !== $eventId) {
            return Response::json(['ok' => false, 'status' => 'wrong_event', 'registration' => $payload,
                'message' => 'Tiket ini untuk event lain: ' . $reg['event_title']], 409);
        }
        if ($reg['checked_in_at']) {
            return Response::json(['ok' => false, 'status' => 'already', 'registration' => $payload,
                'message' => $reg['name'] . ' sudah check-in pada ' . date_id((string) $reg['checked_in_at']) . '.'], 409);
        }
        Registration::checkIn((int) $reg['id'], Auth::id());
        ActivityLog::record('checkin', 'Check-in ' . $reg['name'] . ' (' . $reg['code'] . ')');
        $stats = Registration::stats($eventId);
        return Response::json(['ok' => true, 'status' => 'checked_in', 'registration' => $payload, 'stats' => $stats,
            'message' => 'Selamat datang, ' . $reg['name'] . '!']);
    }

    public function search(Request $request): Response
    {
        $q = mb_substr($request->query('q'), 0, 100);
        if (mb_strlen($q) < 2) {
            return Response::json(['ok' => true, 'results' => []]);
        }
        $rows = Registration::quickSearch($q, $request->int('event'));
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'], 'code' => $r['code'], 'name' => $r['name'], 'wa' => mask_wa((string) $r['wa']),
                'representative' => $r['representative'], 'event' => $r['event_title'],
                'checked_in_at' => $r['checked_in_at'] ? date_id((string) $r['checked_in_at']) : null,
            ];
        }
        return Response::json(['ok' => true, 'results' => $out]);
    }
}
