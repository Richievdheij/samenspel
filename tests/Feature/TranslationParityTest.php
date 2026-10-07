<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

use function Pest\Laravel\getJson;

/**
 * lang/en.json and lang/nl.json must hold the same keys, in BOTH directions.
 *
 * One direction is not enough, and the two failures are different:
 *
 *   a key in en but not nl  -> the interface silently falls back to English for
 *                              one string, on a page that is otherwise Dutch
 *   a key in nl but not en  -> a translation for a string nothing says any more,
 *                              which nobody will ever notice is dead
 *
 * Neither breaks a page, so neither is caught by any other gate in this repository.
 */

/** @return array<string, string> */
$translations = function (string $locale): array {
    $path = lang_path("{$locale}.json");
    $raw = file_get_contents($path);

    throw_if($raw === false, RuntimeException::class, "Could not read {$path}.");

    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    throw_unless(is_array($decoded), RuntimeException::class, "{$path} does not contain a JSON object.");

    // Narrowed by checking, not by a docblock asserting what json_decode cannot
    // promise. A nested object or a numeric value would otherwise reach the
    // comparison below as something neither side can compare.
    $strings = [];

    foreach ($decoded as $key => $value) {
        throw_if(! is_string($key) || ! is_string($value), RuntimeException::class, "{$path} must map strings to strings; found a non-string entry.");

        $strings[$key] = $value;
    }

    return $strings;
};

it('has the same translation keys in en and nl', function () use ($translations): void {
    $en = $translations('en');
    $nl = $translations('nl');

    expect(array_keys(array_diff_key($en, $nl)))
        ->toBe([], 'these keys are in lang/en.json but missing from lang/nl.json');

    expect(array_keys(array_diff_key($nl, $en)))
        ->toBe([], 'these keys are in lang/nl.json but missing from lang/en.json');

    // A non-zero count, so a check over two accidentally-empty files cannot pass.
    expect($en)->not->toBeEmpty();
});

it('has no empty translation on either side', function () use ($translations): void {
    foreach (['en', 'nl'] as $locale) {
        foreach ($translations($locale) as $key => $value) {
            expect(trim($value))->not->toBe('', "lang/{$locale}.json has an empty value for [{$key}]");
        }
    }
});

it('serves the Dutch translation rather than the key', function (): void {
    // Parity is a property of two files; this asserts the files are actually
    // reached. A translation file that is present, complete and never loaded looks
    // identical to a correct one until someone reads the page.
    getJson('/')->assertOk();

    expect(__('Skip to content'))->toBe('Naar de inhoud');
});

/**
 * The same guarantee for the PHP groups — auth, passwords, validation,
 * pagination — whose messages Laravel itself produces. A group or a line missing
 * from lang/nl is a validation error that appears in English on a Dutch page.
 *
 * @return list<string>
 */
$dottedKeys = function (string $locale, string $group): array {
    $lines = require lang_path("{$locale}/{$group}.php");

    throw_unless(is_array($lines), RuntimeException::class, "lang/{$locale}/{$group}.php does not return an array.");

    return array_keys(Arr::dot($lines));
};

it('has the same language groups and lines in lang/en and lang/nl', function () use ($dottedKeys): void {
    $groups = fn (string $locale): array => array_map(
        fn (string $path): string => basename($path, '.php'),
        glob(lang_path("{$locale}/*.php")) ?: [],
    );

    expect($groups('nl'))->toBe($groups('en'))->not->toBeEmpty();

    foreach ($groups('en') as $group) {
        expect($dottedKeys('nl', $group))->toEqualCanonicalizing($dottedKeys('en', $group), "lang/nl/{$group}.php and lang/en/{$group}.php hold different lines");
    }
});
