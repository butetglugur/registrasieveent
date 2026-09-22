<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\ValidationException;
use App\Models\Event;
use App\Models\Registration;
use App\Services\Notifier;

/**
 * Halaman publik: formulir pendaftaran, tiket, kalender.
 */
final class EventController extends Controller
{
    private function findVisible(string $slug): array
    {
        $event = Event::findBySlug($slug);
        // Draft hanya bisa dilihat admin (mode pratinjau).
        if (!$event || ($event['status'] === 'draft' && !Auth::check())) {
            throw new HttpException(404, 'Event tidak ditemukan atau belum dipublikasikan.');
        }
        return $event;
    }

    public function show(Request $request, string $slug): string
    {
        $event = $this->findVisible($slug);
        $count = Registration::countForEvent((int) $event['id']);
        [$open, $reason] = Event::registrationState($event, $count);
        if ($event['status'] === 'draft' && Auth::check()) {
            $open = true; // admin boleh uji coba formulir draft
        }
        return view('public.event', [
            'event'   => $event,
            'fields'  => Event::fields($event),
            'count'   => $count,
            'open'    => $open,
            'reason'  => $reason,
            'isDraft' => $event['status'] === 'draft',
            'formTs'  => self::signTs(time()),
        ]);
    }

    public function register(Request $request, string $slug): Response
    {
        $event = $this->findVisible($slug);
        $back = route('event.show', ['slug' => $slug]);

        [$open, $reason] = Event::registrationState($event);
        if (!$open && !($event['status'] === 'draft' && Auth::check())) {
            return Response::redirect($back)->with('error', $reason);
        }

        // Anti-bot: honeypot + jeda minimal pengisian formulir.
        if ($request->str('website') !== '' || !self::validTs($request->str('ts'))) {
            return Response::redirect($back)->with('error', 'Pengiriman ditolak. Silakan isi formulir kembali secara normal.');
        }

        // Batasi jumlah pendaftaran per IP (aman untuk Wi-Fi bersama di lokasi acara).
        $limit = max(5, (int) setting('submit_limit', '30'));
        $key = 'register|' . client_ip();
        if (RateLimiter::tooMany($key, $limit)) {
            $wait = (int) ceil(RateLimiter::availableIn($key) / 60);
            return Response::redirect($back)->with('error', 'Terlalu banyak pendaftaran dari jaringan Anda. Coba lagi dalam ' . max(1, $wait) . ' menit.');
        }

        $cc = (string) setting('wa_country_code', '62');
        $fields = Event::fields($event);
        $data = [
            'name'           => $request->str('name'),
            'wa'             => $request->str('wa'),
            'email'          => $request->str('email'),
            'address'        => $request->str('address'),
            'representative' => $request->str('representative'),
        ];
        $rules = [
            'name' => 'required|max:120',
            'wa'   => 'required|max:25|wa',
        ];
        $labels = ['name' => 'Nama lengkap', 'wa' => 'Nomor WhatsApp', 'email' => 'Email', 'address' => 'Alamat',
                   'representative' => Event::representativeLabel($event)];
        if ((int) $event['show_email']) {
            $rules['email'] = ((int) $event['require_email'] ? 'required' : 'nullable') . '|email|max:150';
        }
        if ((int) $event['show_address']) {
            $rules['address'] = ((int) $event['require_address'] ? 'required' : 'nullable') . '|max:255';
        }
        if ((int) $event['show_representative']) {
            $rules['representative'] = ((int) $event['require_representative'] ? 'required' : 'nullable') . '|max:150';
        }

        $extraInput = $request->arr('extra');
        $extra = [];
        foreach ($fields as $f) {
            $k = 'extra_' . $f['key'];
            $raw = $extraInput[$f['key']] ?? null;
            if ($f['type'] === 'checkbox') {
                $val = [];
                foreach (is_array($raw) ? $raw : [] as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $val[] = trim($item);
                    }
                }
            } else {
                $val = is_string($raw) ? trim($raw) : '';
            }
            $data[$k] = $val;
            $labels[$k] = $f['label'];
            $r = [$f['required'] ? 'required' : 'nullable'];
            switch ($f['type']) {
                case 'email':
                    array_push($r, 'email', 'max:150');
                    break;
                case 'number':
                    $r[] = 'regex:/^-?\d{1,15}([.,]\d{1,4})?$/';
                    break;
                case 'date':
                    $r[] = 'date';
                    break;
                case 'textarea':
                    $r[] = 'max:1000';
                    break;
                case 'select':
                case 'radio':
                case 'checkbox':
                    break;
                default:
                    $r[] = 'max:255';
            }
            $rules[$k] = $r;
            $extra[$f['key']] = $val;
        }

        $v = Validator::make($data, $rules, $labels);
        $failed = $v->fails();
        // Pilihan wajib berasal dari daftar opsi yang ditentukan admin.
        foreach ($fields as $f) {
            if (!in_array($f['type'], ['select', 'radio', 'checkbox'], true)) {
                continue;
            }
            $vals = (array) $data['extra_' . $f['key']];
            foreach ($vals as $val) {
                if ($val !== '' && !in_array($val, $f['options'], true)) {
                    $v->addError('extra_' . $f['key'], 'Pilihan ' . $f['label'] . ' tidak valid.');
                    $failed = true;
                    break;
                }
            }
        }
        if ($failed) {
            throw new ValidationException($v->errors(), $request->all(), $back);
        }

        $wa = normalize_wa($data['wa'], $cc);

        RateLimiter::hit($key, 600);

        // Transaksi + kunci baris event: kuota & cek duplikat tetap akurat saat ramai.
        $result = DB::transaction(function () use ($event, $data, $wa, $extra, $request) {
            DB::first('SELECT id FROM events WHERE id = ? FOR UPDATE', [(int) $event['id']]);
            if (!empty($event['quota']) && Registration::countForEvent((int) $event['id']) >= (int) $event['quota']) {
                return ['full' => true];
            }
            if ((int) $event['dedupe_wa']) {
                $dup = Registration::findDuplicate((int) $event['id'], $wa);
                if ($dup) {
                    return ['dup' => $dup];
                }
            }
            return ['reg' => Registration::create([
                'event_id'       => (int) $event['id'],
                'name'           => $data['name'],
                'wa'             => $wa,
                'email'          => (int) $event['show_email'] ? ($data['email'] ?: null) : null,
                'address'        => (int) $event['show_address'] ? ($data['address'] ?: null) : null,
                'representative' => (int) $event['show_representative'] ? ($data['representative'] ?: null) : null,
                'extra'          => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
                'ip'             => client_ip(),
                'user_agent'     => $request->userAgent(),
            ])];
        });

        if (isset($result['full'])) {
            return Response::redirect($back)->with('error', 'Kuota peserta sudah penuh. Terima kasih atas antusiasme Anda!');
        }
        if (isset($result['dup'])) {
            $dup = $result['dup'];
            session()->put('dup_' . $event['id'], ['name' => $dup['name'], 'created_at' => $dup['created_at']]);
            return Response::redirect(route('event.already', ['slug' => $slug]));
        }
        $reg = $result['reg'];

        // ⚠️ Notifikasi ke peserta (WA gateway / email / webhook) bila diaktifkan admin.
        // Dikirim SETELAH respons dikirim ke browser agar pendaftar tidak menunggu API eksternal.
        $notifyIds = Notifier::queueForRegistration($reg, $event);
        if ($notifyIds) {
            App::terminating(static function () use ($notifyIds) {
                Notifier::process($notifyIds, 3);
            });
        }

        // Ingat tiket milik pengunjung ini (untuk tampilan "tiket saya").
        $mine = (array) session()->get('my_tickets', []);
        array_unshift($mine, $reg['code']);
        session()->put('my_tickets', array_slice(array_unique($mine), 0, 10));

        return Response::redirect(route('ticket', ['code' => $reg['code']]) . '?baru=1');
    }

    public function already(Request $request, string $slug): string
    {
        $event = $this->findVisible($slug);
        $dup = session()->get('dup_' . $event['id']);
        if (!is_array($dup)) {
            throw new HttpException(404);
        }
        return view('public.already', ['event' => $event, 'dup' => $dup]);
    }

    public function ticket(Request $request, string $code): string
    {
        $reg = Registration::findByCode($code);
        if (!$reg) {
            throw new HttpException(404, 'Tiket tidak ditemukan. Pastikan kode tiket benar.');
        }
        return view('public.ticket', [
            'reg'      => $reg,
            'isNew'    => $request->query('baru') === '1',
            'groupUrl' => safe_url($reg['group_link'] ?? ''),
        ]);
    }

    public function ics(Request $request, string $slug): Response
    {
        $event = $this->findVisible($slug);
        if (empty($event['starts_at'])) {
            throw new HttpException(404, 'Jadwal event belum ditentukan.');
        }
        $fmt = static fn(string $d) => gmdate('Ymd\THis\Z', (int) strtotime($d));
        $esc = static fn(string $s) => str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $s);
        $end = $event['ends_at'] ?: date('Y-m-d H:i:s', (int) strtotime((string) $event['starts_at']) + 7200);
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Presensi Event//ID', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:event-' . $event['id'] . '@' . request()->host(),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $fmt((string) $event['starts_at']),
            'DTEND:' . $fmt((string) $end),
            'SUMMARY:' . $esc((string) $event['title']),
            'LOCATION:' . $esc((string) ($event['location'] ?? '')),
            'DESCRIPTION:' . $esc(str_limit((string) ($event['description'] ?? ''), 500)),
            'URL:' . full_url('e/' . $event['slug']),
            'END:VEVENT', 'END:VCALENDAR',
        ];
        return new Response(implode("\r\n", $lines) . "\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $event['slug'] . '.ics"',
        ]);
    }

    /** Timestamp bertanda tangan untuk time-trap anti-bot. */
    public static function signTs(int $ts): string
    {
        return $ts . '.' . substr(hash_hmac('sha256', (string) $ts, (string) config('app.key', 'k')), 0, 16);
    }

    public static function validTs(string $value, int $minSeconds = 2): bool
    {
        if (!preg_match('/^(\d{9,11})\.([a-f0-9]{16})$/', $value, $m)) {
            return false;
        }
        $ts = (int) $m[1];
        if (!hash_equals(substr(hash_hmac('sha256', (string) $ts, (string) config('app.key', 'k')), 0, 16), $m[2])) {
            return false;
        }
        $age = time() - $ts;
        return $age >= $minSeconds && $age <= 86400 * 2;
    }
}
