<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\User;

final class AccountController extends Controller
{
    public function edit(Request $request): string
    {
        return view('admin.account', ['user' => Auth::user()]);
    }

    public function update(Request $request): Response
    {
        $user = (array) Auth::user();
        $back = route('admin.account');
        $data = $request->only(['name', 'email']);
        $data['current_password'] = (string) $request->input('current_password', '');
        $data['password'] = (string) $request->input('password', '');
        $data['password_confirmation'] = (string) $request->input('password_confirmation', '');

        $rules = ['name' => 'required|max:100', 'email' => 'nullable|email|max:150'];
        if ($data['password'] !== '') {
            $rules['current_password'] = 'required';
            $rules['password'] = 'required|password|same:password_confirmation';
        }
        $this->validate($data, $rules, [
            'name' => 'Nama', 'current_password' => 'Password saat ini', 'password' => 'Password baru',
        ], $back);

        $update = ['name' => $data['name'], 'email' => $data['email'] ?: null];
        if ($data['password'] !== '') {
            if (!password_verify($data['current_password'], (string) $user['password_hash'])) {
                return Response::redirect($back)
                    ->withErrors(['current_password' => ['Password saat ini salah.']], $data)
                    ->with('error', 'Password saat ini salah.');
            }
            $update['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        User::update((int) $user['id'], $update);

        if (isset($update['password_hash'])) {
            // Perbarui sidik jari sesi agar sesi ini tetap login; sesi lain otomatis keluar.
            $fresh = User::find((int) $user['id']);
            Auth::login((array) $fresh);
            ActivityLog::record('password_change', 'Mengganti password sendiri');
        }
        return Response::redirect($back)->with('success', 'Profil diperbarui.');
    }
}
