<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\User;

final class AuthController extends Controller
{
    public function showLogin(Request $request): string
    {
        return view('auth.login');
    }

    public function login(Request $request): Response
    {
        $username = mb_substr($request->str('username'), 0, 60);
        $password = (string) $request->input('password', '');
        if (!is_string($password)) {
            $password = '';
        }
        $ip = client_ip();
        $userKey = 'login|' . strtolower($username) . '|' . $ip;
        $ipKey = 'login-ip|' . $ip;
        $max = (int) config('app.login_max_attempts', 5);
        $decay = (int) config('app.login_decay', 900);

        if (RateLimiter::tooMany($userKey, $max) || RateLimiter::tooMany($ipKey, $max * 4)) {
            $wait = max(RateLimiter::availableIn($userKey), RateLimiter::availableIn($ipKey));
            return Response::redirect(route('login'))
                ->withErrors([], ['username' => $username])
                ->with('error', 'Terlalu banyak percobaan login. Coba lagi dalam ' . max(1, (int) ceil($wait / 60)) . ' menit.');
        }

        $user = ($username !== '' && $password !== '') ? User::attempt($username, $password) : null;
        if (!$user) {
            RateLimiter::hit($userKey, $decay);
            RateLimiter::hit($ipKey, $decay);
            ActivityLog::record('login_failed', 'Login gagal untuk username "' . str_limit($username, 40) . '"', 0);
            return Response::redirect(route('login'))
                ->withErrors([], ['username' => $username])
                ->with('error', 'Username atau password salah.');
        }

        RateLimiter::clear($userKey);
        Auth::login($user);
        DB::update('users', ['last_login_at' => now(), 'last_login_ip' => $ip], 'id = ?', [(int) $user['id']]);
        ActivityLog::record('login', 'Login berhasil', (int) $user['id']);

        $intended = (string) session()->pull('intended', '');
        $adminBase = url('admin');
        $to = ($intended !== '' && str_starts_with($intended, $adminBase) && !str_contains($intended, '//'))
            ? $intended : route('admin.dashboard');
        return Response::redirect($to)->with('success', 'Selamat datang, ' . $user['name'] . '!');
    }

    public function logout(Request $request): Response
    {
        if (Auth::check()) {
            ActivityLog::record('logout', 'Logout');
        }
        Auth::logout();
        return Response::redirect(route('login'))->with('success', 'Anda telah logout.');
    }
}
