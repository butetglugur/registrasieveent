<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\User;

final class UserController extends Controller
{
    public function index(Request $request): string
    {
        return view('admin.users.index', ['users' => User::all()]);
    }

    public function store(Request $request): Response
    {
        $data = $request->only(['name', 'username', 'email', 'role']);
        $data['password'] = (string) $request->input('password', '');
        $back = route('admin.users');
        $this->validate($data, [
            'name'     => 'required|max:100',
            'username' => 'required|username',
            'email'    => 'nullable|email|max:150',
            'role'     => 'required|in:' . implode(',', array_keys(User::ROLES)),
            'password' => 'required|password',
        ], ['name' => 'Nama', 'username' => 'Username', 'role' => 'Peran', 'password' => 'Password'], $back);

        if (User::findByUsername($data['username'])) {
            return Response::redirect($back)->withErrors(['username' => ['Username sudah dipakai.']], $data)
                ->with('error', 'Username sudah dipakai.');
        }
        User::create($data);
        ActivityLog::record('user_create', 'Menambah pengguna ' . $data['username'] . ' (' . $data['role'] . ')');
        return Response::redirect($back)->with('success', 'Pengguna ' . $data['username'] . ' ditambahkan.');
    }

    public function update(Request $request, string $id): Response
    {
        $user = User::find((int) $id);
        if (!$user) {
            throw new HttpException(404);
        }
        $back = route('admin.users');
        $role = $request->str('role');
        $active = $request->bool('is_active') ? 1 : 0;
        $password = (string) $request->input('password', '');
        if (!isset(User::ROLES[$role])) {
            throw new HttpException(400);
        }
        $isSelf = (int) $user['id'] === Auth::id();
        if ($isSelf && ($role !== 'admin' || !$active)) {
            return Response::redirect($back)->with('error', 'Anda tidak dapat menurunkan peran atau menonaktifkan akun sendiri.');
        }
        if ($user['role'] === 'admin' && ($role !== 'admin' || !$active) && User::countAdmins() <= 1) {
            return Response::redirect($back)->with('error', 'Harus ada minimal satu Administrator aktif.');
        }
        $update = ['role' => $role, 'is_active' => $active];
        if ($password !== '') {
            $this->validate(['password' => $password], ['password' => 'password'], ['password' => 'Password baru'], $back);
            $update['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        User::update((int) $user['id'], $update);
        ActivityLog::record('user_update', 'Memperbarui pengguna ' . $user['username']
            . ($password !== '' ? ' (reset password)' : ''));
        return Response::redirect($back)->with('success', 'Pengguna ' . $user['username'] . ' diperbarui.');
    }

    public function destroy(Request $request, string $id): Response
    {
        $user = User::find((int) $id);
        if (!$user) {
            throw new HttpException(404);
        }
        if ((int) $user['id'] === Auth::id()) {
            return Response::redirect(route('admin.users'))->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }
        if ($user['role'] === 'admin' && User::countAdmins() <= 1) {
            return Response::redirect(route('admin.users'))->with('error', 'Harus ada minimal satu Administrator aktif.');
        }
        User::delete((int) $user['id']);
        ActivityLog::record('user_delete', 'Menghapus pengguna ' . $user['username']);
        return Response::redirect(route('admin.users'))->with('success', 'Pengguna ' . $user['username'] . ' dihapus.');
    }
}
