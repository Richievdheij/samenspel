<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Resolve Dutch addresses against PDOK's address register, never user text alone. */
final class AddressLookup
{
    /** @return list<array{id: string, label: string, street: string, number: string, postcode: string, city: string, type: string}> */
    public function suggest(string $query): array
    {
        /** @var list<array{id: string, label: string, street: string, number: string, postcode: string, city: string, type: string}> $results */
        $results = Cache::remember('pdok:suggest:'.hash('sha256', mb_strtolower(trim($query))), now()->addHours(12), function () use ($query): array {
            $results = [];
            foreach ($this->documents('/suggest', ['q' => $query, 'fq' => 'type:adres', 'rows' => 8]) as $document) {
                $location = $this->location($document);
                if ($location !== null) {
                    $results[] = $location;
                }
            }

            return $results;
        });

        return $results;
    }

    /** @return array{id: string, label: string, street: string, number: string, postcode: string, city: string, type: string}|null */
    public function lookup(string $id): ?array
    {
        /** @var array{id: string, label: string, street: string, number: string, postcode: string, city: string, type: string}|null $location */
        $location = Cache::remember('pdok:location:'.$id, now()->addDays(7), function () use ($id): ?array {
            foreach ($this->documents('/lookup', ['id' => $id]) as $document) {
                if (($document['id'] ?? null) === $id) {
                    return $this->location($document);
                }
            }

            return null;
        });

        return $location;
    }

    /**
     * @param  array<string, int|string>  $query
     * @return list<array<string, mixed>>
     */
    private function documents(string $path, array $query): array
    {
        /** @var string $baseUrl */
        $baseUrl = config('services.pdok.base_url');
        $data = Http::acceptJson()->timeout(5)->get($baseUrl.$path, $query)->throw()->json('response.docs');

        if (! is_array($data)) {
            return [];
        }

        $documents = [];
        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $document = [];
            foreach ($item as $key => $value) {
                if (is_string($key)) {
                    $document[$key] = $value;
                }
            }
            $documents[] = $document;
        }

        return $documents;
    }

    /** @param array<string, mixed> $document
     * @return array{id: string, label: string, street: string, number: string, postcode: string, city: string, type: string}|null
     */
    private function location(array $document): ?array
    {
        $id = $document['id'] ?? null;
        $label = $document['weergavenaam'] ?? null;
        $type = $document['type'] ?? null;
        if ($type !== 'adres' || ! is_string($id) || ! is_string($label) || $label === '' || mb_strlen($label) > 255) {
            return null;
        }

        return [
            'id' => $id,
            'label' => $label,
            'street' => $this->documentString($document, 'straatnaam'),
            'number' => $this->documentString($document, 'huisnummer').$this->documentString($document, 'huisletter').$this->documentString($document, 'huisnummertoevoeging'),
            'postcode' => $this->documentString($document, 'postcode'),
            'city' => $this->documentString($document, 'woonplaatsnaam'),
            'type' => $type,
        ];
    }

    /** @param array<string, mixed> $document */
    private function documentString(array $document, string $key): string
    {
        $value = $document[$key] ?? null;

        return is_string($value) || is_int($value) ? (string) $value : '';
    }
}
