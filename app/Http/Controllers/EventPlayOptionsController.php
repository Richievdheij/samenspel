<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEventPlayOptionsRequest;
use App\Models\Event;
use App\Models\Game;
use App\Models\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class EventPlayOptionsController extends Controller
{
    public function edit(Request $request, Event $event): View
    {
        abort_unless($request->user()?->id === $event->user_id, 403);
        $event->load(['games', 'platforms']);

        return view('events.edit-play-options', [
            'event' => $event,
            'games' => Game::query()->where(function ($query) use ($event): void {
                $query->selectable()->orWhereIn('id', $event->games->modelKeys());
            })->orderBy('name')->get(['id', 'name', 'platform', 'cover_image_url', 'cover_image_path', 'logo_image_path', 'provider_id']),
            'platforms' => Platform::query()->orderBy('id')->get(),
        ]);
    }

    public function update(UpdateEventPlayOptionsRequest $request, Event $event): RedirectResponse
    {
        $event->updatePlayOptions($request->ids('games'), $request->ids('platforms'));

        return to_route('events.show', $event)->with('status', __('Games and platforms updated.'));
    }
}
