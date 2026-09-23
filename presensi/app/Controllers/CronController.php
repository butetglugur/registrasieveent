<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Notifier;

/**
 * Endpoint cron via URL (alternatif bila perintah PHP CLI tidak tersedia di panel).
 * Contoh cron DirectAdmin: wget -q -O /dev/null "https://domain/cron/TOKEN"
 */
final class CronController extends Controller
{
    public function run(Request $request, string $token): Response
    {
        if (!hash_equals(Notifier::cronToken(), $token)) {
            throw new HttpException(404);
        }
        if (!Notifier::enabled()) {
            return Response::json(['ok' => true, 'message' => 'Notifikasi nonaktif']);
        }
        @set_time_limit(60);
        $r = Notifier::runFor(max(5, min(50, $request->int('detik', 25))));
        return Response::json(['ok' => true] + $r);
    }
}
