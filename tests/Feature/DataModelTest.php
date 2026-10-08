<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Game;
use Illuminate\Database\UniqueConstraintViolationException;

/*
 * The data model in docs/erd.md, held to what the database itself guarantees:
 * the relations, the unique sign-up and what deleting a row takes with it.
 */

it('refuses a second game or category with the same name', function (string $model): void {
    $model::query()->create(['name' => 'LAN']);
    $model::query()->create(['name' => 'LAN']);
})->with([Game::class, Category::class])->throws(UniqueConstraintViolationException::class);
