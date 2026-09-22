<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Models\ActivityLog;

final class ActivityController extends Controller
{
    public function index(Request $request): string
    {
        $action = $request->query('action');
        if (random_int(1, 20) === 1) {
            ActivityLog::prune(180);
        }
        return view('admin.activity', [
            'logs'    => ActivityLog::paginate(max(1, $request->int('page', 1)), 30, $action),
            'actions' => ActivityLog::actions(),
            'action'  => $action,
        ]);
    }
}
