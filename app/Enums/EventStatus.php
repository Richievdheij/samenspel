<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether an event takes sign-ups. Only the organiser switches it. "Full" is not
 * a status: it follows from the number of sign-ups against max_participants.
 */
enum EventStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
