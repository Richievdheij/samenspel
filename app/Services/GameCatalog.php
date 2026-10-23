<?php

declare(strict_types=1);

namespace App\Services;

interface GameCatalog
{
    /**
     * The titles a search may contain. The IGDB query embeds the term, so every
     * search is checked against this restricted alphabet before it is sent.
     */
    public const string TITLE_PATTERN = '/^[\pL\pN][\pL\pN .:’\'&+_-]*$/uD';

    /** @return list<array{id: int, name: string, platform: string|null, platforms: list<string>, release_year: int|null, cover_url: string|null}> */
    public function search(string $term): array;

    /** @return array{id: int, name: string, platform: string|null, platforms: list<string>, release_year: int|null, cover_url: string|null}|null */
    public function find(int $id): ?array;

    /** @return array{id: int, name: string, platform: string|null, platforms: list<string>, release_year: int|null, cover_url: string|null}|null */
    public function fresh(int $id): ?array;
}
