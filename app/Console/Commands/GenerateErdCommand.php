<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Draws the data model as a Mermaid erDiagram in docs/erd.md.
 *
 * The schema is read from a throwaway SQLite database that `erd:draw` migrates
 * in a process of its own, never from the connection in .env. Two developers on
 * two different local databases would otherwise draw two different diagrams, and
 * the test that compares the file against the migrations would fail for one of
 * them.
 *
 * Only the block between the markers is rewritten. Everything around it is prose
 * somebody wrote, and a generator that overwrites prose gets switched off.
 */
#[Signature('erd:generate
    {--check : Fail when the diagram is out of date, without writing anything}
    {--path=docs/erd.md : The document holding the diagram, relative to the project root}')]
#[Description('Regenerate the Mermaid ERD in docs/erd.md from the migrations')]
final class GenerateErdCommand extends Command
{
    public const string START = '<!-- erd:start -->';

    public const string END = '<!-- erd:end -->';

    /**
     * What a new document starts as. Only used when the file does not exist yet;
     * from then on the prose is the developer's and is never rewritten.
     */
    private const string NEW_DOCUMENT = <<<'MARKDOWN'
        # Data model

        The tables the migrations create, drawn as a Mermaid `erDiagram` that GitHub
        renders in place.

        The block between the `erd:start` and `erd:end` markers is generated. Run
        `make erd` after changing a migration and commit the result; `make test` fails
        while the diagram and the migrations disagree. Everything outside the markers
        is yours to write: what an entity means, why a column exists, what is planned.

        How to read it:

        - `PK`, `FK` and `UK` mark the primary key, a foreign key and a single-column
          unique index. A column marked `nullable` accepts null.
        - A solid line is a foreign key the database enforces. A dotted line is a
          relation inferred from Laravel's naming convention, such as a `user_id`
          column next to a `users` table, with no constraint behind it. A
          polymorphic `*_id` and `*_type` pair gets no line, because it can point
          at any table.
        - `|o` on the parent side means the reference is nullable. `o|` on the child
          side means it is unique, so a parent has at most one such child.
        - Types are normalised to `int`, `string`, `text`, `datetime`, `bool` and so
          on. The diagram is drawn from a throwaway SQLite database so that every
          machine draws the same one, which means it shows the kind of each column
          rather than the exact type your production database uses.

        MARKDOWN;

    public function handle(): int
    {
        $relativePath = $this->relativePath();
        $path = base_path($relativePath);
        // The blank lines are Prettier's: without them `make format` rewrites the
        // block, and the next check reports a diagram that never changed.
        $block = self::START."\n\n```mermaid\n".$this->diagram()."```\n\n".self::END;

        if (! File::exists($path)) {
            if ($this->option('check')) {
                $this->components->error("{$relativePath} does not exist. Run `make erd` to create it.");

                return self::FAILURE;
            }

            File::ensureDirectoryExists(dirname($path));
            File::put($path, self::NEW_DOCUMENT."\n".$block."\n");
            $this->components->info("Created {$relativePath}.");

            return self::SUCCESS;
        }

        $current = File::get($path);
        $start = strpos($current, self::START);
        $end = strpos($current, self::END);

        if ($start === false || $end === false || $end < $start) {
            $this->components->error(sprintf(
                '%s has no %s ... %s block, so there is nowhere to put the diagram. Add the two markers where it belongs.',
                $relativePath,
                self::START,
                self::END,
            ));

            return self::FAILURE;
        }

        $updated = substr($current, 0, $start).$block.substr($current, $end + strlen(self::END));

        if ($updated === $current) {
            $this->components->info("{$relativePath} matches the migrations.");

            return self::SUCCESS;
        }

        if ($this->option('check')) {
            $this->components->error("{$relativePath} does not match the migrations. Run `make erd` and commit the result.");

            return self::FAILURE;
        }

        File::put($path, $updated);
        $this->components->info("Updated the diagram in {$relativePath}.");

        return self::SUCCESS;
    }

    private function relativePath(): string
    {
        $path = $this->option('path');

        throw_unless(is_string($path) && $path !== '', RuntimeException::class, 'The --path option needs a value.');

        return $path;
    }

    /**
     * Draw the diagram in a process of its own; DrawErdCommand says why.
     */
    private function diagram(): string
    {
        $output = storage_path('framework/erd-'.Str::uuid().'.mmd');

        try {
            $result = Process::path(base_path())->run([PHP_BINARY, 'artisan', 'erd:draw', $output]);

            throw_unless(
                $result->successful(),
                RuntimeException::class,
                "Drawing the diagram failed:\n".trim($result->errorOutput()."\n".$result->output()),
            );

            return File::get($output);
        } finally {
            File::delete($output);
        }
    }
}
