<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\View\View;

class EventController extends Controller
{
    /**
     * The upcoming events, soonest first. Open to guests.
     *
     * The game, category and sign-up count come along in the same few queries,
     * not one extra query per card.
     */
    public function index(): View
    {
        return view('events.index', [
            'events' => Event::query()
                ->upcoming()
                ->with(['game', 'category'])
                ->withCount('participants')
                ->oldest('starts_at')
                ->get(),
        ]);
    }
}
