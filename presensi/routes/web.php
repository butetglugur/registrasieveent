<?php

/**
 * Definisi route (mirip routes/web.php Laravel).
 * @var App\Core\Router $r
 */

use App\Controllers\Admin\AccountController;
use App\Controllers\Admin\ActivityController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CheckinController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\EventController as AdminEventController;
use App\Controllers\Admin\RegistrationController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\UserController;
use App\Controllers\EventController;
use App\Controllers\HomeController;
use App\Controllers\InstallController;

// ---------- Installer (otomatis terkunci setelah instalasi) ----------
$r->get('/install', [InstallController::class, 'index'])->name('install');
$r->post('/install', [InstallController::class, 'store'])->name('install.store');

// ---------- Publik ----------
$r->get('/', [HomeController::class, 'index'])->name('home');
$r->get('/e/{slug}', [EventController::class, 'show'])->name('event.show');
$r->post('/e/{slug}', [EventController::class, 'register'])->name('event.register');
$r->get('/e/{slug}/terdaftar', [EventController::class, 'already'])->name('event.already');
$r->get('/e/{slug}/kalender.ics', [EventController::class, 'ics'])->name('event.ics');
$r->get('/t/{code}', [EventController::class, 'ticket'])->name('ticket');

// Kompatibilitas URL versi lama
$r->get('/index.php', [HomeController::class, 'index']);
$r->get('/thanks.php', [HomeController::class, 'index']);

// ---------- Admin: tamu ----------
$r->group(['prefix' => '/admin', 'middleware' => ['guest']], function ($r) {
    $r->get('/login', [AuthController::class, 'showLogin'])->name('login');
    $r->post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

$r->post('/admin/logout', [AuthController::class, 'logout'])->name('logout');

// ---------- Admin: semua user login ----------
$r->group(['prefix' => '/admin', 'middleware' => ['auth']], function ($r) {
    $r->get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

    $r->get('/peserta', [RegistrationController::class, 'index'])->name('admin.registrations');
    $r->get('/peserta/export', [RegistrationController::class, 'export'])->name('admin.registrations.export');
    $r->get('/peserta/{id}', [RegistrationController::class, 'show'])->name('admin.registrations.show');
    $r->post('/peserta/{id}/checkin', [RegistrationController::class, 'toggleCheckin'])->name('admin.registrations.checkin');

    $r->get('/checkin', [CheckinController::class, 'index'])->name('admin.checkin');
    $r->post('/checkin', [CheckinController::class, 'store'])->name('admin.checkin.store');
    $r->get('/checkin/cari', [CheckinController::class, 'search'])->name('admin.checkin.search');

    $r->get('/akun', [AccountController::class, 'edit'])->name('admin.account');
    $r->post('/akun', [AccountController::class, 'update'])->name('admin.account.update');
});

// ---------- Admin: khusus Administrator ----------
$r->group(['prefix' => '/admin', 'middleware' => ['auth', 'admin']], function ($r) {
    $r->get('/event', [AdminEventController::class, 'index'])->name('admin.events');
    $r->get('/event/baru', [AdminEventController::class, 'create'])->name('admin.events.create');
    $r->post('/event', [AdminEventController::class, 'store'])->name('admin.events.store');
    $r->get('/event/{id}/edit', [AdminEventController::class, 'edit'])->name('admin.events.edit');
    $r->post('/event/{id}', [AdminEventController::class, 'update'])->name('admin.events.update');
    $r->post('/event/{id}/hapus', [AdminEventController::class, 'destroy'])->name('admin.events.destroy');
    $r->post('/event/{id}/duplikat', [AdminEventController::class, 'duplicate'])->name('admin.events.duplicate');
    $r->post('/event/{id}/status', [AdminEventController::class, 'status'])->name('admin.events.status');

    $r->get('/peserta/{id}/edit', [RegistrationController::class, 'edit'])->name('admin.registrations.edit');
    $r->post('/peserta/{id}', [RegistrationController::class, 'update'])->name('admin.registrations.update');
    $r->post('/peserta/{id}/hapus', [RegistrationController::class, 'destroy'])->name('admin.registrations.destroy');
    $r->post('/peserta-massal', [RegistrationController::class, 'bulk'])->name('admin.registrations.bulk');

    $r->get('/pengguna', [UserController::class, 'index'])->name('admin.users');
    $r->post('/pengguna', [UserController::class, 'store'])->name('admin.users.store');
    $r->post('/pengguna/{id}', [UserController::class, 'update'])->name('admin.users.update');
    $r->post('/pengguna/{id}/hapus', [UserController::class, 'destroy'])->name('admin.users.destroy');

    $r->get('/pengaturan', [SettingController::class, 'edit'])->name('admin.settings');
    $r->post('/pengaturan', [SettingController::class, 'update'])->name('admin.settings.update');

    $r->get('/aktivitas', [ActivityController::class, 'index'])->name('admin.activity');
});
