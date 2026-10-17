<?php

declare(strict_types=1);

namespace App\Services;

interface GameCatalog
{
    /** @return list<array{id: int, name: string, platform: string|null, platforms: list<string>, release_year: int|null, cover_url: string|null}> */
    public function search(string $term): array;

    /** @return array{id: int, name: string, platform: string|null, platforms: list<string>, release_year: int|null, cover_url: string|null}|null */
    public function find(int $id): ?array;

    /** @return array{id: int, name: string, platform: string|null, platforms: list<string>, release_year: int|null, cover_url: string|null}|null */
    public function fresh(int $id): ?array;
}
