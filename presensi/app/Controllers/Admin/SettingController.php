<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Setting;

final class SettingController extends Controller
{
    public function edit(Request $request): string
    {
        return view('admin.settings.edit', ['events' => Event::options()]);
    }

    public function update(Request $request): Response
    {
        $data = $request->only([
            'app_name', 'org_name', 'tagline', 'footer_text', 'home_mode', 'home_event_id',
            'wa_country_code', 'submit_limit', 'default_theme',
        ]);
        $this->validate($data, [
            'app_name'        => 'required|max:60',
            'org_name'        => 'nullable|max:100',
            'tagline'         => 'nullable|max:200',
            'footer_text'     => 'nullable|max:200',
            'home_mode'       => 'required|in:list,event',
            'home_event_id'   => 'nullable|integer',
            'wa_country_code' => 'required|regex:/^[1-9]\d{0,3}$/',
            'submit_limit'    => 'required|integer|numeric|min:5|max:1000',
            'default_theme'   => 'required|in:' . implode(',', array_keys(themes())),
        ], [
            'app_name' => 'Nama aplikasi', 'org_name' => 'Nama organisasi', 'home_mode' => 'Halaman depan',
            'wa_country_code' => 'Kode negara WA', 'submit_limit' => 'Batas pendaftaran per IP', 'default_theme' => 'Tema default',
        ], route('admin.settings'));

        if ($data['home_mode'] === 'event' && !Event::find((int) $data['home_event_id'])) {
            return Response::redirect(route('admin.settings'))
                ->withErrors(['home_event_id' => ['Pilih event yang akan ditampilkan.']], $data)
                ->with('error', 'Pilih event untuk halaman depan.');
        }
        $data['show_count_public'] = $request->bool('show_count_public') ? '1' : '0';
        Setting::setMany($data);
        ActivityLog::record('settings_update', 'Memperbarui pengaturan aplikasi');
        return Response::redirect(route('admin.settings'))->with('success', 'Pengaturan disimpan.');
    }
}
