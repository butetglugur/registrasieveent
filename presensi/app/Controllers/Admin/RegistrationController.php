<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\XlsxWriter;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Registration;

final class RegistrationController extends Controller
{
    private function filters(Request $request): array
    {
        return [
            'event_id' => $request->int('event'),
            'q'        => mb_substr($request->query('q'), 0, 100),
            'status'   => in_array($request->query('status'), ['in', 'out'], true) ? $request->query('status') : '',
            'from'     => $request->query('from'),
            'to'       => $request->query('to'),
        ];
    }

    private function findOrFail(string $id): array
    {
        $reg = Registration::find((int) $id);
        if (!$reg) {
            throw new HttpException(404, 'Data peserta tidak ditemukan.');
        }
        return $reg;
    }

    public function index(Request $request): string
    {
        $filters = $this->filters($request);
        $perPage = in_array($request->int('per'), [10, 25, 50, 100], true) ? $request->int('per') : 25;
        $sort = $request->query('sort', 'newest');
        $page = max(1, $request->int('page', 1));
        $event = $filters['event_id'] ? Event::find($filters['event_id']) : null;
        return view('admin.registrations.index', [
            'list'    => Registration::paginate($filters, $page, $perPage, $sort),
            'filters' => $filters,
            'events'  => Event::options(),
            'event'   => $event,
            'stats'   => Registration::stats($filters['event_id']),
            'perPage' => $perPage,
            'sort'    => $sort,
        ]);
    }

    public function show(Request $request, string $id): string
    {
        $reg = $this->findOrFail($id);
        $checker = $reg['checked_in_by'] ? \App\Models\User::find((int) $reg['checked_in_by']) : null;
        return view('admin.registrations.show', [
            'reg'     => $reg,
            'fields'  => Event::fields(['fields' => $reg['event_fields']]),
            'extra'   => Registration::extra($reg),
            'checker' => $checker,
        ]);
    }

    public function edit(Request $request, string $id): string
    {
        $reg = $this->findOrFail($id);
        return view('admin.registrations.edit', [
            'reg'    => $reg,
            'fields' => Event::fields(['fields' => $reg['event_fields']]),
            'extra'  => Registration::extra($reg),
            'events' => Event::options(),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $reg = $this->findOrFail($id);
        $back = route('admin.registrations.edit', ['id' => $reg['id']]);
        $data = $request->only(['name', 'wa', 'email', 'address', 'representative']);
        $data['event_id'] = (string) $request->int('event_id');
        $this->validate($data, [
            'name'           => 'required|max:120',
            'wa'             => 'required|max:25|wa',
            'email'          => 'nullable|email|max:150',
            'address'        => 'nullable|max:255',
            'representative' => 'nullable|max:150',
            'event_id'       => 'required|integer',
        ], ['name' => 'Nama', 'wa' => 'Nomor WhatsApp', 'representative' => 'Perwakilan', 'address' => 'Alamat', 'event_id' => 'Event'], $back);

        if (!Event::find((int) $data['event_id'])) {
            return Response::redirect($back)->with('error', 'Event tujuan tidak ditemukan.');
        }

        $fields = Event::fields(['fields' => $reg['event_fields']]);
        $extraIn = $request->arr('extra');
        $extra = Registration::extra($reg);
        foreach ($fields as $f) {
            $raw = $extraIn[$f['key']] ?? null;
            if ($f['type'] === 'checkbox') {
                $extra[$f['key']] = array_values(array_intersect($f['options'], is_array($raw) ? $raw : []));
            } else {
                $extra[$f['key']] = is_string($raw) ? mb_substr(trim($raw), 0, 1000) : '';
            }
        }

        Registration::update((int) $reg['id'], [
            'event_id'       => (int) $data['event_id'],
            'name'           => $data['name'],
            'wa'             => normalize_wa($data['wa'], (string) setting('wa_country_code', '62')),
            'email'          => $data['email'] ?: null,
            'address'        => $data['address'] ?: null,
            'representative' => $data['representative'] ?: null,
            'extra'          => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
        ]);
        ActivityLog::record('registration_update', 'Mengubah data peserta ' . $data['name'] . ' (' . $reg['code'] . ')');
        return Response::redirect(route('admin.registrations.show', ['id' => $reg['id']]))->with('success', 'Data peserta diperbarui.');
    }

    public function destroy(Request $request, string $id): Response
    {
        $reg = $this->findOrFail($id);
        Registration::delete((int) $reg['id']);
        ActivityLog::record('registration_delete', 'Menghapus peserta ' . $reg['name'] . ' (' . $reg['code'] . ')');
        $to = $request->str('redirect') === 'list' ? back(route('admin.registrations')) : Response::redirect(route('admin.registrations'));
        return $to->with('success', 'Peserta "' . $reg['name'] . '" dihapus.');
    }

    public function toggleCheckin(Request $request, string $id): Response
    {
        $reg = $this->findOrFail($id);
        if ($reg['checked_in_at']) {
            Registration::undoCheckIn((int) $reg['id']);
            ActivityLog::record('checkin_undo', 'Membatalkan check-in ' . $reg['name'] . ' (' . $reg['code'] . ')');
            $msg = 'Check-in ' . $reg['name'] . ' dibatalkan.';
        } else {
            Registration::checkIn((int) $reg['id'], Auth::id());
            ActivityLog::record('checkin', 'Check-in ' . $reg['name'] . ' (' . $reg['code'] . ')');
            $msg = $reg['name'] . ' berhasil check-in.';
        }
        if ($request->wantsJson()) {
            $fresh = Registration::find((int) $reg['id']);
            return Response::json(['ok' => true, 'message' => $msg, 'checked_in_at' => $fresh['checked_in_at'] ?? null]);
        }
        return back(route('admin.registrations.show', ['id' => $reg['id']]))->with('success', $msg);
    }

    public function bulk(Request $request): Response
    {
        $ids = array_map('intval', $request->arr('ids'));
        $action = $request->str('action');
        if (!$ids) {
            return back(route('admin.registrations'))->with('warning', 'Pilih minimal satu peserta.');
        }
        if ($action === 'delete') {
            $n = Registration::deleteMany($ids);
            ActivityLog::record('registration_delete', 'Menghapus ' . $n . ' peserta sekaligus');
            return back(route('admin.registrations'))->with('success', $n . ' peserta dihapus.');
        }
        if ($action === 'checkin') {
            $n = Registration::checkInMany($ids, Auth::id());
            ActivityLog::record('checkin', 'Check-in massal ' . $n . ' peserta');
            return back(route('admin.registrations'))->with('success', $n . ' peserta ditandai hadir.');
        }
        throw new HttpException(400);
    }

    public function export(Request $request): Response
    {
        if (!is_admin()) {
            throw new HttpException(403, 'Export data hanya untuk Administrator.');
        }
        $filters = $this->filters($request);
        $format = ($request->query('format') === 'csv' || !XlsxWriter::available()) ? 'csv' : 'xlsx';
        $event = $filters['event_id'] ? Event::find($filters['event_id']) : null;
        $fields = $event ? Event::fields($event) : [];
        $name = 'peserta-' . ($event ? $event['slug'] : 'semua') . '-' . date('Ymd-His') . '.' . $format;

        ActivityLog::record('export', 'Export ' . strtoupper($format) . ' data peserta'
            . ($event ? ' event "' . $event['title'] . '"' : ' (semua event)'));

        $rows = static function () use ($filters, $fields, $event): \Generator {
            $head = ['No', 'Kode Tiket', 'Event', 'Nama', 'WhatsApp', 'Email', 'Alamat', 'Perwakilan'];
            foreach ($fields as $f) {
                $head[] = $f['label'];
            }
            if (!$event) {
                $head[] = 'Data Tambahan';
            }
            array_push($head, 'Waktu Daftar', 'Status Hadir', 'Waktu Check-in');
            yield $head;

            $stmt = Registration::cursor($filters);
            $i = 0;
            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $extra = Registration::extra($r);
                $row = [++$i, $r['code'], $r['event_title'], $r['name'], $r['wa'], $r['email'], $r['address'], $r['representative']];
                foreach ($fields as $f) {
                    $v = $extra[$f['key']] ?? '';
                    $row[] = is_array($v) ? implode(', ', $v) : $v;
                }
                if (!$event) {
                    $parts = [];
                    foreach ($extra as $k => $v) {
                        $parts[] = $k . ': ' . (is_array($v) ? implode(', ', $v) : $v);
                    }
                    $row[] = implode(' | ', $parts);
                }
                array_push($row, $r['created_at'], $r['checked_in_at'] ? 'Hadir' : 'Belum', $r['checked_in_at'] ?? '');
                yield $row;
            }
        };

        if ($format === 'xlsx') {
            @set_time_limit(300);
            $x = new XlsxWriter();
            $first = true;
            foreach ($rows() as $row) {
                $x->addRow($row, $first);
                $first = false;
            }
            $file = $x->finish();
            return Response::stream(static function () use ($file) {
                readfile($file);
                @unlink($file);
            }, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $name . '"',
                'Content-Length'      => (string) filesize($file),
                'Cache-Control'       => 'no-store',
            ]);
        }

        return Response::stream(static function () use ($rows) {
            @set_time_limit(300);
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            foreach ($rows() as $row) {
                fputcsv($out, array_map('csv_safe', $row), ',', '"', '');
            }
            fclose($out);
        }, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }
}
