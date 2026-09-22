<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Event;

final class HomeController extends Controller
{
    /** @return string|Response */
    public function index(Request $request)
    {
        if (setting('home_mode') === 'event') {
            $event = Event::find((int) setting('home_event_id', '0'));
            if ($event && $event['status'] !== 'draft') {
                return Response::redirect(route('event.show', ['slug' => $event['slug']]));
            }
        }
        $events = Event::publicList();
        return view('public.home', ['events' => $events]);
    }
}
