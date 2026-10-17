<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

final class IgdbGameCatalog implements GameCatalog
{
    private const string MAIN_GAME_FIELDS = 'fields id,name,game_type,game_status,parent_game,version_parent,involved_companies,first_release_date,total_rating_count,hypes,platforms.name,cover.image_id;';

    private const string MAIN_GAME_FILTER = 'game_type = 0 & parent_game = null & version_parent = null & involved_companies != null & first_release_date != null';

    /** IGDB game_status values for offline, cancelled and rumoured games: nothing to play at an event. */
    private const array UNPLAYABLE_STATUSES = [5, 6, 7];

    /** Seasons, chapters, packs, DLC and marketing editions that IGDB occasionally files as a main game. */
    private const string NON_GAME_TITLE = '/\b(?:season|chapter)\s+(?:\d+|one|two|three|four|five|six|seven|eight|nine|ten)\b|\b(?:starter|quest|cosmetic|skin|content|crew)\s+pack\b|\bbattle\s+pass\b|\b(?:dlc|add-?on|soundtrack)\b|:\s+[^:]*\b(?:pack|bundle)\b|\b(?:deluxe|gold|ultimate|complete|collector\'?s|premium|standard|definitive|goty|game\s+of\s+the\s+year|digital|legendary|champions|special|anniversary|founder\'?s|limited|platinum|launch|vault|cross-gen)\s+edition\b/iu';

    private const int SEARCH_CANDIDATES = 50;

    private const int SEARCH_RESULTS = 10;

    public function search(string $term): array
    {
        $key = 'igdb:main-game:v4:search:'.hash('sha256', mb_strtolower($term));

        /** @var list<array{id: int, name: string, platform: string|null, platforms: list<string>, release_year: int|null, cover_url: string|null}> $games */
        $games = Cache::remember($key, now()->addMinutes(30),
            // Input is validated to a restricted alphabet before it reaches this query.
            fn (): array => $this->request('search "'.$term.'"; '.self::MAIN_GAME_FIELDS.' where '.self::MAIN_GAME_FILTER.'; limit '.self::SEARCH_CANDIDATES.';', $term));

        return $games;
    }

    public function find(int $id): ?array
    {
        /** @var list<array{id: int, name: string, platform: string|null, platforms: list<string>, release_year: int|null, cover_url: string|null}> $games */
        $games = Cache::remember('igdb:main-game:v4:'.$id, now()->addHours(6), fn (): array => $this->request(
            self::MAIN_GAME_FIELDS.' where id = '.$id.' & '.self::MAIN_GAME_FILTER.'; limit 1;',
        ));

        return $games[0] ?? null;
    }

    public function fresh(int $id): ?array
    {
        $games = $this->request(self::MAIN_GAME_FIELDS.' where id = '.$id.' & '.self::MAIN_GAME_FILTER.'; limit 1;');
        Cache::put('igdb:main-game:v4:'.$id, $games, now()->addHours(6));

        return $games[0] ?? null;
    }

    /**
     * Fetch main games, and for a search rank them the way a person expects.
     *
     * IGDB's own relevance puts fan projects and spin-offs above the game itself, so a
     * search ranks an exact title first, then titles starting with the term, then titles
     * containing every word, then the rest (matched on an alternative name such as
     * "GTA"), each by how many people rated it. A title nobody rated or anticipated only
     * appears when it is the exact title searched for.
     *
     * @return list<array{id: int, name: string, platform: string|null, platforms: list<string>, release_year: int|null, cover_url: string|null}>
     */
    private function request(string $query, ?string $term = null): array
    {
        $clientId = config('services.igdb.client_id');
        throw_if(! is_string($clientId) || $clientId === '', GameCatalogUnavailable::class);
        throw_if(RateLimiter::tooManyAttempts('igdb:global', 4), GameCatalogUnavailable::class);
        RateLimiter::hit('igdb:global', 1);

        try {
            $response = Http::withHeaders([
                'Client-ID' => $clientId,
                'Authorization' => 'Bearer '.$this->token(),
            ])->acceptJson()->timeout(5)->withBody($query, 'text/plain')->post('https://api.igdb.com/v4/games');
            if ($response->status() === 401) {
                Cache::forget('igdb:oauth:'.hash('sha256', $clientId));
                throw_if(RateLimiter::tooManyAttempts('igdb:global', 4), GameCatalogUnavailable::class);
                RateLimiter::hit('igdb:global', 1);
                $response = Http::withHeaders([
                    'Client-ID' => $clientId,
                    'Authorization' => 'Bearer '.$this->token(),
                ])->acceptJson()->timeout(5)->withBody($query, 'text/plain')->post('https://api.igdb.com/v4/games');
            }
            throw_if(! $response->successful() || ! is_array($response->json()), GameCatalogUnavailable::class);
        } catch (ConnectionException) {
            throw new GameCatalogUnavailable;
        }

        $ranked = [];
        foreach ($response->json() as $item) {
            throw_if(! is_array($item) || ! is_int($item['id'] ?? null) || ! is_string($item['name'] ?? null), GameCatalogUnavailable::class);
            if (($item['game_type'] ?? null) !== 0 || isset($item['parent_game']) || isset($item['version_parent']) || ! is_array($item['involved_companies'] ?? null) || $item['involved_companies'] === []
                || ! is_int($item['first_release_date'] ?? null) || in_array($item['game_status'] ?? null, self::UNPLAYABLE_STATUSES, true)) {
                continue;
            }
            $name = trim($item['name']);
            throw_if($name === '' || mb_strlen($name) > 255, GameCatalogUnavailable::class);
            if (preg_match(self::NON_GAME_TITLE, $name) === 1) {
                continue;
            }
            $ratings = is_int($item['total_rating_count'] ?? null) ? $item['total_rating_count'] : 0;
            $hypes = is_int($item['hypes'] ?? null) ? $item['hypes'] : 0;
            $match = $term === null ? 0 : $this->match($name, $term);
            if ($term !== null && $match < 3 && $ratings === 0 && $hypes === 0) {
                continue;
            }
            $platforms = [];
            if (is_array($item['platforms'] ?? null)) {
                foreach ($item['platforms'] as $value) {
                    if (is_array($value) && is_string($value['name'] ?? null)) {
                        $platforms[] = mb_substr(trim($value['name']), 0, 255);
                    }
                }
            }
            $platforms = array_values(array_unique(array_filter($platforms)));
            $image = is_array($item['cover'] ?? null) ? ($item['cover']['image_id'] ?? null) : null;
            $ranked[] = ['match' => $match, 'ratings' => $ratings, 'game' => [
                'id' => $item['id'],
                'name' => $name,
                'platform' => $platforms[0] ?? null,
                'platforms' => $platforms,
                'release_year' => (int) gmdate('Y', $item['first_release_date']),
                'cover_url' => is_string($image) && preg_match('/^[a-zA-Z0-9_]+$/D', $image) === 1
                    ? 'https://images.igdb.com/igdb/image/upload/t_cover_big/'.$image.'.jpg' : null,
            ]];
        }

        if ($term === null) {
            return array_column($ranked, 'game');
        }

        // usort is stable, so IGDB's relevance still breaks a tie.
        usort($ranked, fn (array $a, array $b): int => [$b['match'], $b['ratings']] <=> [$a['match'], $a['ratings']]);

        return array_slice(array_column($ranked, 'game'), 0, self::SEARCH_RESULTS);
    }

    /** 3 for the exact title, 2 for a title starting with the term, 1 for one containing all its words, else 0. */
    private function match(string $name, string $term): int
    {
        $normalise = fn (string $value): string => trim(preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($value)) ?? '');
        $name = $normalise($name);
        $term = $normalise($term);

        return match (true) {
            $name === $term => 3,
            str_starts_with($name, $term) => 2,
            array_diff(explode(' ', $term), explode(' ', $name)) === [] => 1,
            default => 0,
        };
    }

    private function token(): string
    {
        $clientId = config('services.igdb.client_id');
        $secret = config('services.igdb.client_secret');
        throw_if(! is_string($clientId) || $clientId === '' || ! is_string($secret) || $secret === '', GameCatalogUnavailable::class);

        $key = 'igdb:oauth:'.hash('sha256', $clientId);
        $cached = Cache::get($key);
        if (is_string($cached)) {
            return $cached;
        }

        try {
            $response = Http::asForm()->timeout(5)->post('https://id.twitch.tv/oauth2/token', [
                'client_id' => $clientId,
                'client_secret' => $secret,
                'grant_type' => 'client_credentials',
            ]);
        } catch (ConnectionException) {
            throw new GameCatalogUnavailable;
        }
        $token = $response->json('access_token');
        $expires = $response->json('expires_in');
        throw_if(! $response->successful() || ! is_string($token) || $token === '' || ! is_int($expires) || $expires < 120, GameCatalogUnavailable::class);
        Cache::put($key, $token, $expires - 60);

        return $token;
    }
}
