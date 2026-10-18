<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CancelSignUpRequest;
use App\Http\Requests\SignUpForEventRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/** Signing up for an event, and cancelling that sign-up, as a participant. */
final class EventSignUpController extends Controller
{
    /**
     * The request has checked the event; signUp() checks the places again under a
     * lock, for the moment someone else took the last one in between.
     */
    public function store(SignUpForEventRequest $request, Event $event): RedirectResponse
    {
        $refusal = $event->signUp($request->signedInUser());
        if ($refusal !== null) {
            throw ValidationException::withMessages(['sign_up' => $refusal]);
        }

        return back(fallback: route('events.show', $event))
            ->with('status', __('You are signed up for :title.', ['title' => $event->title]));
    }

    /** Cancel the sign-up, which frees the place for someone else. */
    public function destroy(CancelSignUpRequest $request, Event $event): RedirectResponse
    {
        $event->participants()->detach($request->signedInUser());

        return back(fallback: route('events.show', $event))
            ->with('status', __('You are no longer signed up for :title. Your place is free again.', ['title' => $event->title]));
    }
}
