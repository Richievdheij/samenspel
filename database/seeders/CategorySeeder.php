<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The starting categories. firstOrCreate keeps it safe to run on a database that
 * already has them.
 */
final class CategorySeeder extends Seeder
{
    /** @var list<string> */
    public const array NAMES = [
        'Casual',
        'Competitief',
        'LAN',
        'Online',
    ];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            Category::query()->firstOrCreate(['name' => $name]);
        }
    }
}
