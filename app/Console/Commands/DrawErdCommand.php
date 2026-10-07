<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Hidden;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Writes the Mermaid erDiagram of the migrations to the file it is given. `erd:generate` runs this in a
 * disposable process of its own; it is not meant to be called in-process.
 *
 * Every configured connection is pointed at one throwaway SQLite file before
 * anything is migrated. Pointing only the default connection there is not enough:
 * a migration may name its own connection (Telescope's does), and that migration
 * would then run against the real database from .env. In a separate process this
 * rewiring cannot leak, and it is why this is a command of its own rather than a
 * method that `erd:generate` calls: inside a test it would disconnect the test
 * database mid-transaction.
 *
 * The diagram goes to a file, not to stdout. Console output is not a reliable
 * channel here: laravel/pao rewrites it when an agent runs the command, which
 * collapses the indentation and would make the drift test depend on who runs it.
 */
#[Hidden]
#[Signature('erd:draw {output : The file to write the diagram to}')]
#[Description('Write the Mermaid ERD of the migrations to a file (run by erd:generate in its own process)')]
final class DrawErdCommand extends Command
{
    /** Laravel's own bookkeeping, not part of the data model. */
    private const array IGNORED_TABLES = ['migrations'];

    public function handle(): int
    {
        // Under storage/, which is gitignored, with a name nobody can predict.
        // SQLite refuses to open a file that does not exist yet.
        $database = storage_path('framework/erd-'.Str::uuid().'.sqlite');
        File::put($database, '');

        try {
            $this->pointEveryConnectionAt($database);
            $this->refuseSchemaDumpSqliteCannotLoad();

            $exitCode = $this->callSilently('migrate', ['--force' => true]);

            throw_unless($exitCode === self::SUCCESS, RuntimeException::class, 'The migrations failed against SQLite; run `php artisan migrate --database=sqlite` against a scratch database to see why.');

            $output = $this->argument('output');

            File::put($output, $this->render(Schema::connection('sqlite')));

            return self::SUCCESS;
        } finally {
            DB::purge('sqlite');
            File::delete($database);
        }
    }

    private function pointEveryConnectionAt(string $database): void
    {
        $sqlite = [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];

        $connections = config('database.connections');
        $names = is_array($connections) ? array_keys($connections) : [];

        foreach ([...$names, 'sqlite'] as $name) {
            config(["database.connections.{$name}" => $sqlite]);
            DB::purge((string) $name);
        }

        config(['database.default' => 'sqlite']);
    }

    /**
     * After `schema:dump --prune` the early migrations are gone and only the dump
     * remains. A MySQL or PostgreSQL dump cannot be loaded into SQLite, so drawing
     * would silently leave out every table it holds.
     */
    private function refuseSchemaDumpSqliteCannotLoad(): void
    {
        $dumps = File::glob(database_path('schema/*-schema.{sql,dump}'), GLOB_BRACE);
        $foreign = array_filter($dumps, fn (string $dump): bool => ! str_starts_with(basename($dump), 'sqlite-schema.'));

        throw_if(
            $foreign !== [] && ! File::exists(database_path('schema/sqlite-schema.sql')) && ! File::exists(database_path('schema/sqlite-schema.dump')),
            RuntimeException::class,
            'The migrations were squashed into '.implode(', ', array_map(basename(...), $foreign)).', which SQLite cannot load, so the diagram would miss those tables. Add a database/schema/sqlite-schema.sql as well.',
        );
    }

    private function render(Builder $schema): string
    {
        $tables = array_values(array_diff(
            array_map(fn (array $table): string => $table['name'], $schema->getTables()),
            self::IGNORED_TABLES,
        ));
        sort($tables);

        $entities = [];
        $relations = [];

        foreach ($tables as $table) {
            $columns = $schema->getColumns($table);
            $names = array_column($columns, 'name');

            $primary = [];
            $unique = [];

            /** @var list<list<string>> $uniqueSets every column set at most one row can hold, sorted */
            $uniqueSets = [];

            foreach ($schema->getIndexes($table) as $index) {
                if ($index['primary']) {
                    $primary = $index['columns'];
                } elseif ($index['unique'] && count($index['columns']) === 1) {
                    $unique[] = $index['columns'][0];
                }

                if ($index['primary'] || $index['unique']) {
                    $uniqueSets[] = $this->sorted($index['columns']);
                }
            }

            /** @var array<string, bool> $nullable */
            $nullable = array_column($columns, 'nullable', 'name');

            /** @var list<array{parent: string, columns: list<string>, enforced: bool}> $references */
            $references = [];

            foreach ($schema->getForeignKeys($table) as $foreignKey) {
                $references[] = ['parent' => $foreignKey['foreign_table'], 'columns' => $foreignKey['columns'], 'enforced' => true];
            }

            $constrained = array_merge([], ...array_column($references, 'columns'));

            foreach ($names as $name) {
                $parent = $this->conventionalParent($name, $names, $tables);

                if ($parent !== null && ! in_array($name, $constrained, true)) {
                    $references[] = ['parent' => $parent, 'columns' => [$name], 'enforced' => false];
                }
            }

            $referencing = array_merge([], ...array_column($references, 'columns'));

            $lines = [];

            foreach ($columns as $column) {
                $keys = array_keys(array_filter([
                    'PK' => in_array($column['name'], $primary, true),
                    'FK' => in_array($column['name'], $referencing, true),
                    'UK' => in_array($column['name'], $unique, true),
                ]));

                $lines[] = '        '.implode(' ', array_filter([
                    $this->typeOf($column),
                    $column['name'],
                    implode(', ', $keys),
                    $column['nullable'] ? '"nullable"' : '',
                ], fn (string $part): bool => $part !== ''));
            }

            // Quoted, because a table may be called `end`, `class` or `one`, and
            // an unquoted keyword stops GitHub from rendering the whole diagram.
            $entities[] = "    \"{$table}\" {\n".implode("\n", $lines)."\n    }";

            foreach ($references as $reference) {
                $optional = array_filter($reference['columns'], fn (string $name): bool => $nullable[$name] ?? false) !== [];
                $single = in_array($this->sorted($reference['columns']), $uniqueSets, true);

                $relations[] = sprintf(
                    '    "%s" %s%s%s "%s" : "%s"',
                    $reference['parent'],
                    $optional ? '|o' : '||',
                    $reference['enforced'] ? '--' : '..',
                    $single ? 'o|' : 'o{',
                    $table,
                    implode(', ', $reference['columns']),
                );
            }
        }

        sort($relations);

        return "erDiagram\n".implode("\n", [...$entities, ...$relations])."\n";
    }

    /**
     * The table a `<name>_id` column points at by Laravel's naming convention,
     * when that table exists. A `<name>_id` next to a `<name>_type` is one half of
     * a polymorphic pair and can point at any table, so it gets no line.
     *
     * @param  list<string>  $columns
     * @param  list<string>  $tables
     */
    private function conventionalParent(string $column, array $columns, array $tables): ?string
    {
        if (! str_ends_with($column, '_id')) {
            return null;
        }

        $name = Str::beforeLast($column, '_id');

        if (in_array("{$name}_type", $columns, true)) {
            return null;
        }

        $parent = Str::plural($name);

        return in_array($parent, $tables, true) ? $parent : null;
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function sorted(array $columns): array
    {
        sort($columns);

        return $columns;
    }

    /**
     * The kind of a column, independent of the database that stores it.
     *
     * @param  array{type: string, type_name: string}  $column
     */
    private function typeOf(array $column): string
    {
        if (str_starts_with(strtolower($column['type']), 'tinyint(1)')) {
            return 'bool';
        }

        $type = strtolower($column['type_name']);

        return match ($type) {
            'integer', 'int', 'bigint', 'smallint', 'mediumint', 'tinyint' => 'int',
            'varchar', 'char' => 'string',
            'text', 'tinytext', 'mediumtext', 'longtext', 'clob' => 'text',
            'datetime', 'timestamp' => 'datetime',
            'numeric', 'decimal' => 'decimal',
            'float', 'double', 'real' => 'float',
            'blob', 'binary', 'varbinary', 'longblob' => 'binary',
            'boolean' => 'bool',
            'jsonb' => 'json',
            default => preg_replace('/\W+/', '_', $type) ?: 'unknown',
        };
    }
}
