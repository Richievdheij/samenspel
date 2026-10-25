<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Who may change an event. Reading is public, so only the writes are checked
 * here. They belong to the organiser, with one deliberate exception: an admin
 * may close or delete any event, to stop misuse. An admin does not edit someone
 * else's event; moderating is stopping it, not rewriting it.
 */
final class EventPolicy
{
    /** Only the organiser edits an event, through the page or a direct request. */
    public function update(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }

    /** The organiser opens or closes sign-ups; an admin may too, to moderate. */
    public function toggleStatus(User $user, Event $event): bool
    {
        return $user->id === $event->user_id || $user->isAdmin();
    }

    /** The organiser deletes an event, its sign-ups with it; an admin may too, to moderate. */
    public function delete(User $user, Event $event): bool
    {
        return $user->id === $event->user_id || $user->isAdmin();
    }
}
