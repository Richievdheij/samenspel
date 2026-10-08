<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\View\View;

class EventController extends Controller
{
    /**
     * The upcoming events, soonest first. Open to guests.
     */
    public function index(): View
    {
        return view('events.index', [
            'events' => Event::query()->upcoming()->oldest('starts_at')->get(),
        ]);
    }
}
