<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\ValidationException;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Setting;
use App\Services\Notifier;
use App\Services\WaThrottle;

/**
 * Pesan massal WhatsApp ke peserta event (mis. pengingat H-1).
 * ⚠️ Mengirim data peserta ke WA gateway pihak ketiga. Semua pesan lewat antrean
 * berjeda anti-blokir — TIDAK dikirim sekaligus.
 */
final class BroadcastController extends Controller
{
    public const MAX_RECIPIENTS = 2000;
    public const AUDIENCES = ['all' => 'Semua peserta', 'out' => 'Belum hadir', 'in' => 'Sudah hadir'];
    public const DEFAULT_TEMPLATE = "{Halo|Hai} {nama} 👋\n\n{Mengingatkan|Sekadar mengingatkan}, *{event}* akan berlangsung:\n🗓 {tanggal}\n📍 {lokasi}\n\nTiket & QR Anda: {link_tiket}\n\n{Sampai jumpa!|Sampai bertemu di lokasi!}";

    private static function recipients(int $eventId, string $audience): array
    {
        $sql = 'SELECT * FROM registrations WHERE event_id = ?';
        if ($audience === 'in') {
            $sql .= ' AND checked_in_at IS NOT NULL';
        } elseif ($audience === 'out') {
            $sql .= ' AND checked_in_at IS NULL';
        }
        $rows = DB::select($sql . ' ORDER BY id ASC LIMIT ' . (self::MAX_RECIPIENTS + 1), [$eventId]);
        return array_values(array_filter($rows, static fn($r) => valid_wa((string) $r['wa'])));
    }

    public function create(Request $request): string
    {
        $eventId = $request->int('event');
        $event = $eventId ? Event::find($eventId) : null;
        $counts = [];
        if ($event) {
            foreach (array_keys(self::AUDIENCES) as $a) {
                $n = count(self::recipients((int) $event['id'], $a));
                $counts[$a] = ['n' => $n, 'eta' => WaThrottle::humanDuration(WaThrottle::estimateSeconds($n))];
            }
        }
        return view('admin.broadcast', [
            'events'   => Event::options(),
            'event'    => $event,
            'counts'   => $counts,
            'ready'    => Notifier::enabled() && (string) Setting::get('notify_wa_provider', 'none') !== 'none',
            'pending'  => Notification::pendingCount('whatsapp'),
        ]);
    }

    public function store(Request $request): Response
    {
        $eventId = $request->int('event_id');
        $back = route('admin.broadcast', [], ['event' => $eventId ?: null]);
        if (!Notifier::enabled() || (string) Setting::get('notify_wa_provider', 'none') === 'none') {
            return Response::redirect($back)->with('error', 'Aktifkan notifikasi & pilih provider WhatsApp di menu Notifikasi terlebih dahulu.');
        }
        $data = ['event_id' => (string) $eventId, 'audience' => $request->str('audience'), 'message' => ''];
        $raw = $request->input('message', '');
        $data['message'] = is_string($raw) ? trim(str_replace("\r\n", "\n", $raw)) : '';
        $v = Validator::make($data, [
            'event_id' => 'required|integer',
            'audience' => 'required|in:' . implode(',', array_keys(self::AUDIENCES)),
            'message'  => 'required|min:10|max:2000',
        ], ['event_id' => 'Event', 'audience' => 'Penerima', 'message' => 'Isi pesan']);
        $v->fails();
        if (!$request->bool('confirm')) {
            $v->addError('confirm', 'Centang konfirmasi terlebih dahulu.');
        }
        $event = Event::find($eventId);
        if (!$event) {
            $v->addError('event_id', 'Event tidak ditemukan.');
        }
        if ($v->errors()) {
            throw new ValidationException($v->errors(), $request->all(), $back);
        }

        $list = self::recipients((int) $event['id'], $data['audience']);
        if (!$list) {
            return Response::redirect($back)->with('warning', 'Tidak ada penerima dengan nomor WhatsApp valid.');
        }
        if (count($list) > self::MAX_RECIPIENTS) {
            return Response::redirect($back)->with('error', 'Penerima lebih dari ' . self::MAX_RECIPIENTS . '. Pecah pengiriman per status/event.');
        }

        $provider = (string) Setting::get('notify_wa_provider');
        DB::transaction(static function () use ($list, $event, $data, $provider) {
            foreach ($list as $reg) {
                Notification::create([
                    'registration_id' => (int) $reg['id'], 'kind' => 'broadcast', 'channel' => 'whatsapp', 'provider' => $provider,
                    'recipient' => (string) $reg['wa'],
                    // Variasi kalimat per penerima agar pesan tidak identik (mengurangi deteksi spam)
                    'message' => WaThrottle::spin(Notifier::render($data['message'], Notifier::vars($reg, $event))),
                ]);
            }
        });
        $eta = WaThrottle::humanDuration(WaThrottle::estimateSeconds(Notification::pendingCount('whatsapp')));
        ActivityLog::record('broadcast', 'Pesan massal WA ke ' . count($list) . ' peserta "' . $event['title'] . '" (' . self::AUDIENCES[$data['audience']] . ')');
        return Response::redirect(route('admin.notifications', [], ['status' => 'pending']))->with(
            'success',
            count($list) . ' pesan masuk antrean dan dikirim bertahap dengan jeda anti-blokir (perkiraan selesai ' . $eta . ').'
            . ' Biarkan halaman ini terbuka atau aktifkan cron agar pengiriman berjalan.'
        );
    }
}
