<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Registration;

final class EventController extends Controller
{
    private function findOrFail(string $id): array
    {
        $event = Event::find((int) $id);
        if (!$event) {
            throw new HttpException(404, 'Event tidak ditemukan.');
        }
        return $event;
    }

    public function index(Request $request): string
    {
        $status = $request->query('status');
        $q = $request->query('q');
        return view('admin.events.index', [
            'events' => Event::allWithStats($status, $q),
            'status' => $status,
            'q'      => $q,
            'counts' => Event::countByStatus(),
        ]);
    }

    public function create(Request $request): string
    {
        $event = [
            'id' => 0, 'title' => '', 'slug' => '', 'subtitle' => '', 'description' => '', 'location' => '',
            'starts_at' => '', 'ends_at' => '', 'status' => 'open', 'quota' => '', 'closes_at' => '',
            'group_link' => '', 'success_message' => '', 'redirect_seconds' => 0,
            'theme' => (string) setting('default_theme', 'violet'), 'dedupe_wa' => 1, 'send_reminder' => 1,
            'show_email' => 0, 'show_address' => 1, 'show_representative' => 1,
            'require_email' => 0, 'require_address' => 0, 'require_representative' => 0,
            'representative_label' => '', 'fields' => '[]',
        ];
        return view('admin.events.form', ['event' => $event, 'fields' => []]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, 0, route('admin.events.create'));
        $id = Event::create($data, Auth::id());
        ActivityLog::record('event_create', 'Membuat event "' . $data['title'] . '"');
        return Response::redirect(route('admin.events.edit', ['id' => $id]))
            ->with('success', 'Event berhasil dibuat. Bagikan tautan pendaftaran kepada peserta!');
    }

    public function edit(Request $request, string $id): string
    {
        $event = $this->findOrFail($id);
        return view('admin.events.form', [
            'event'  => $event,
            'fields' => Event::fields($event),
            'stats'  => Registration::stats((int) $event['id']),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $event = $this->findOrFail($id);
        $data = $this->payload($request, (int) $event['id'], route('admin.events.edit', ['id' => $event['id']]));
        Event::update((int) $event['id'], $data);
        ActivityLog::record('event_update', 'Memperbarui event "' . $data['title'] . '"');
        return Response::redirect(route('admin.events.edit', ['id' => $event['id']]))->with('success', 'Perubahan disimpan.');
    }

    public function destroy(Request $request, string $id): Response
    {
        $event = $this->findOrFail($id);
        $confirm = $request->str('confirm');
        if ($confirm !== $event['slug']) {
            return Response::redirect(route('admin.events'))
                ->with('error', 'Konfirmasi tidak cocok. Ketik slug event "' . $event['slug'] . '" untuk menghapus.');
        }
        $total = Registration::countForEvent((int) $event['id']);
        Event::delete((int) $event['id']);
        ActivityLog::record('event_delete', 'Menghapus event "' . $event['title'] . '" beserta ' . $total . ' peserta');
        return Response::redirect(route('admin.events'))->with('success', 'Event "' . $event['title'] . '" dihapus.');
    }

    public function duplicate(Request $request, string $id): Response
    {
        $event = $this->findOrFail($id);
        $newId = Event::duplicate((int) $event['id'], Auth::id());
        ActivityLog::record('event_create', 'Menduplikasi event "' . $event['title'] . '"');
        return Response::redirect(route('admin.events.edit', ['id' => $newId]))
            ->with('success', 'Event diduplikasi sebagai draft. Silakan sesuaikan lalu buka pendaftaran.');
    }

    public function status(Request $request, string $id): Response
    {
        $event = $this->findOrFail($id);
        $status = $request->str('status');
        if (!isset(Event::STATUSES[$status])) {
            throw new HttpException(400);
        }
        Event::update((int) $event['id'], ['status' => $status]);
        ActivityLog::record('event_update', 'Status event "' . $event['title'] . '" menjadi ' . Event::STATUSES[$status]);
        return back(route('admin.events'))->with('success', 'Status event: ' . Event::STATUSES[$status] . '.');
    }

    /** Validasi & susun data event dari form. */
    private function payload(Request $request, int $id, string $back): array
    {
        $in = $request->only([
            'title', 'slug', 'subtitle', 'description', 'location', 'starts_at', 'ends_at', 'status', 'quota',
            'closes_at', 'group_link', 'success_message', 'redirect_seconds', 'theme', 'representative_label',
        ]);
        $in['fields'] = (string) $request->input('fields', '[]');

        $v = Validator::make($in, [
            'title'            => 'required|max:150',
            'slug'             => 'nullable|max:80|slug',
            'subtitle'         => 'nullable|max:200',
            'description'      => 'nullable|max:5000',
            'location'         => 'nullable|max:200',
            'starts_at'        => 'nullable|date',
            'ends_at'          => 'nullable|date',
            'status'           => 'required|in:' . implode(',', array_keys(Event::STATUSES)),
            'quota'            => 'nullable|integer|numeric|min:1|max:1000000',
            'closes_at'        => 'nullable|date',
            'group_link'       => 'nullable|url|max:255',
            'success_message'  => 'nullable|max:1000',
            'redirect_seconds' => 'nullable|integer|numeric|min:0|max:60',
            'theme'            => 'required|in:' . implode(',', array_keys(themes())),
            'representative_label' => 'nullable|max:60',
        ], [
            'title' => 'Judul', 'slug' => 'Slug URL', 'subtitle' => 'Subjudul', 'description' => 'Deskripsi',
            'location' => 'Lokasi', 'starts_at' => 'Waktu mulai', 'ends_at' => 'Waktu selesai', 'quota' => 'Kuota',
            'closes_at' => 'Batas pendaftaran', 'group_link' => 'Link grup WhatsApp', 'success_message' => 'Pesan sukses',
            'redirect_seconds' => 'Redirect otomatis', 'theme' => 'Tema', 'representative_label' => 'Label perwakilan',
        ]);
        $v->fails();
        $starts = $in['starts_at'] !== '' ? Validator::parseDate($in['starts_at']) : null;
        $ends = $in['ends_at'] !== '' ? Validator::parseDate($in['ends_at']) : null;
        if ($starts && $ends && strtotime($ends) < strtotime($starts)) {
            $v->addError('ends_at', 'Waktu selesai harus setelah waktu mulai.');
        }
        $slug = $in['slug'] !== '' ? $in['slug'] : slugify($in['title']);
        if ($in['slug'] !== '' && Event::uniqueSlug($slug, $id) !== $slug) {
            $v->addError('slug', 'Slug "' . $slug . '" sudah dipakai event lain.');
        }
        if ($v->errors()) {
            throw new \App\Core\ValidationException($v->errors(), $request->all(), $back);
        }

        $fields = Event::sanitizeFields($in['fields']);

        return [
            'title'            => $in['title'],
            'slug'             => Event::uniqueSlug($slug ?: 'event', $id),
            'subtitle'         => $in['subtitle'] ?: null,
            'description'      => $in['description'] ?: null,
            'location'         => $in['location'] ?: null,
            'starts_at'        => $starts,
            'ends_at'          => $ends,
            'status'           => $in['status'],
            'quota'            => $in['quota'] !== '' ? (int) $in['quota'] : null,
            'closes_at'        => $in['closes_at'] !== '' ? Validator::parseDate($in['closes_at']) : null,
            'group_link'       => safe_url($in['group_link']) ?: null,
            'success_message'  => $in['success_message'] ?: null,
            'redirect_seconds' => (int) $in['redirect_seconds'],
            'theme'            => $in['theme'],
            'dedupe_wa'        => $request->bool('dedupe_wa') ? 1 : 0,
            'send_reminder'    => $request->bool('send_reminder') ? 1 : 0,
            'show_email'       => $request->bool('show_email') ? 1 : 0,
            'show_address'     => $request->bool('show_address') ? 1 : 0,
            'show_representative' => $request->bool('show_representative') ? 1 : 0,
            'require_email'    => $request->bool('require_email') ? 1 : 0,
            'require_address'  => $request->bool('require_address') ? 1 : 0,
            'require_representative' => $request->bool('require_representative') ? 1 : 0,
            'representative_label' => $in['representative_label'] ?: null,
            'fields'           => json_encode($fields, JSON_UNESCAPED_UNICODE),
        ];
    }
}
