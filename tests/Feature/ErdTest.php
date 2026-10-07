<?php

declare(strict_types=1);

use App\Console\Commands\GenerateErdCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;

/**
 * docs/erd.md is generated from the migrations, and this file is what keeps it
 * that way. A diagram that drifts from the schema is worse than none: it is read
 * with the confidence of documentation and is wrong.
 *
 * The other cases run against a scratch document under storage/, so they never
 * touch the real one.
 */
const SCRATCH_ERD = 'storage/framework/testing/erd.md';

afterEach(function (): void {
    File::delete(base_path(SCRATCH_ERD));
});

/**
 * `artisan()` is typed as returning an exit code too, which it only does when
 * console mocking is off. Narrowed by checking, so a change there fails loudly.
 *
 * @param  array<string, bool|string>  $options
 */
$erd = function (array $options = []): PendingCommand {
    $command = artisan('erd:generate', $options);

    throw_unless($command instanceof PendingCommand, RuntimeException::class, 'Console output is not mocked, so the command cannot be asserted on.');

    return $command;
};

$scratch = function (string $contents): string {
    $path = base_path(SCRATCH_ERD);
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);

    return $path;
};

it('keeps docs/erd.md in step with the migrations', function () use ($erd): void {
    $erd(['--check' => true])
        ->expectsOutputToContain('matches the migrations')
        ->assertSuccessful();
});

it('refuses a stale diagram without rewriting it', function () use ($erd, $scratch): void {
    $stale = "# Ours\n\n".GenerateErdCommand::START."\nerDiagram\n".GenerateErdCommand::END."\n";
    $path = $scratch($stale);

    $erd(['--check' => true, '--path' => SCRATCH_ERD])
        ->expectsOutputToContain('does not match the migrations')
        ->assertFailed();

    expect(File::get($path))->toBe($stale);
});

it('redraws only the block between the markers', function () use ($erd, $scratch): void {
    $path = $scratch("# Ours\n\nProse we wrote.\n\n".GenerateErdCommand::START."\nold\n".GenerateErdCommand::END."\n\nMore prose.\n");

    $erd(['--path' => SCRATCH_ERD])->assertSuccessful();

    expect(File::get($path))
        ->toStartWith("# Ours\n\nProse we wrote.\n\n".GenerateErdCommand::START)
        ->toEndWith(GenerateErdCommand::END."\n\nMore prose.\n")
        ->toContain('erDiagram', '"users" |o..o{ "sessions" : "user_id"')
        ->not->toContain("\nold\n");

    $erd(['--check' => true, '--path' => SCRATCH_ERD])->assertSuccessful();
});

it('refuses a document without markers rather than overwrite it', function () use ($erd, $scratch): void {
    $path = $scratch("# Ours\n\nNo markers here.\n");

    $erd(['--path' => SCRATCH_ERD])
        ->expectsOutputToContain('nowhere to put the diagram')
        ->assertFailed();

    expect(File::get($path))->toBe("# Ours\n\nNo markers here.\n");
});

it('creates the document when it does not exist yet', function () use ($erd): void {
    $erd(['--check' => true, '--path' => SCRATCH_ERD])->assertFailed();
    expect(File::exists(base_path(SCRATCH_ERD)))->toBeFalse();

    $erd(['--path' => SCRATCH_ERD])->assertSuccessful();

    expect(File::get(base_path(SCRATCH_ERD)))->toContain(GenerateErdCommand::START, 'erDiagram', GenerateErdCommand::END);
});
