<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 */
#[WithoutTimestamps]
final class Platform extends Model
{
    /** @return BelongsToMany<Event, $this> */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class);
    }
}
