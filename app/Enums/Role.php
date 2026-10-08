<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a user may do. Stored as a string in users.role, so adding a role is a
 * new case here and never a database migration.
 */
enum Role: string
{
    case User = 'user';
    case Admin = 'admin';
}
