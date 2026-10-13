<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\View\View;

class EventController extends Controller
{
    /**
     * The upcoming events, soonest first, ten to a page. Open to guests.
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
                ->paginate(10),
        ]);
    }

    /** Show one event, its organiser, a small avatar preview and one page of participants. */
    public function show(Event $event): View
    {
        $event->load(['game', 'category', 'organizer'])
            ->loadCount('participants');

        return view('events.show', [
            'event' => $event,
            'participantPreview' => $event->participants()->orderBy('users.name')->limit(4)->get(['users.id', 'users.name']),
            'participants' => $event->participants()
                ->orderBy('users.name')
                ->paginate(8, ['users.id', 'users.name'], 'participants_page')
                ->fragment('event-participants'),
        ]);
    }
}
