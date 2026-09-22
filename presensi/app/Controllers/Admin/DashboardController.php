<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Registration;

final class DashboardController extends Controller
{
    public function index(Request $request): string
    {
        $events = Event::allWithStats();
        $activeEvents = array_values(array_filter($events, static fn($e) => $e['status'] === 'open'));
        return view('admin.dashboard', [
            'stats'        => Registration::stats(),
            'eventCounts'  => Event::countByStatus(),
            'daily'        => Registration::daily(14),
            'recent'       => Registration::recent(8),
            'events'       => array_slice($activeEvents ?: $events, 0, 5),
            'topReps'      => Registration::topRepresentatives(0, 6),
            'activity'     => is_admin() ? ActivityLog::recent(6) : [],
        ]);
    }
}
