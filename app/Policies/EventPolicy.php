<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Who may change an event. Reading is public, so only the writes are checked
 * here, and every one of them belongs to the organiser alone.
 */
final class EventPolicy
{
    /** Only the organiser edits an event, through the page or a direct request. */
    public function update(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }
}
