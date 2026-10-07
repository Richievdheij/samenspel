<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use RectorLaravel\Rector\ClassMethod\MakeModelAttributesAndScopesProtectedRector;
use RectorLaravel\Set\LaravelSetList;

/*
 * Rector is scoped to the three directories this project actually writes:
 * app/, database/ and tests/.
 *
 * config/ and routes/ are deliberately absent. They are largely Laravel's own
 * scaffolding, and a rule that rewrites a config file produces a diff nobody
 * asked for in a file whose upstream version is the reference. bootstrap/ is
 * absent for the same reason.
 *
 * `make rector` applies changes; `make rector-check` is the dry run that CI
 * gates on, so a rule that WOULD change committed code fails the build instead of
 * quietly rewriting it during a review.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/database',
        __DIR__.'/tests',
    ])

    // Bound to the versions actually installed, rather than a hardcoded
    // LARAVEL_130 constant that silently means the wrong thing after an upgrade.
    ->withComposerBased(laravel: true)

    ->withPhpSets()

    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )

    ->withSets([
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_IF_HELPERS,
    ])

    /*
     * Three rules are not run. These are not suppressions of a defect report —
     * Rector reports nothing, it rewrites — they are refactorings that would each
     * break something deliberate, with the reason recorded so the next person does
     * not re-enable them and find out the hard way.
     */
    ->withSkip([
        /*
         * Deletes a parameter it believes is unused. In a framework the signature
         * IS the contract: it stripped `?User $user` from ExamplePolicy::viewAny()
         * and `Example $example` from ::update(), which is exactly how Laravel's
         * Gate passes its arguments. The result still parses, still passes every
         * other gate, and authorises the wrong thing.
         */
        RemoveUnusedPublicMethodParameterRector::class,

        /*
         * Rewrites `public function scopePublished` to `protected`. It works,
         * because scopes are reached through __call — but every Laravel tutorial,
         * the framework's own documentation, and the generated ide-helper docblock
         * say public. A template that quietly disagrees with the documentation its
         * readers will find is a template that costs them an afternoon.
         */
        MakeModelAttributesAndScopesProtectedRector::class,

        /*
         * Converts 'Illuminate\Http\Request' to Request::class. Correct almost
         * everywhere, and wrong in an architecture test: those name namespaces and
         * classes as STRINGS on purpose, and importing a class solely to forbid its
         * use reads backwards.
         */
        StringClassNameToClassConstantRector::class => [
            __DIR__.'/tests/Arch',
        ],
    ])

    // Rector and Pint both have an opinion about layout, and Pint owns it. Rector
    // changes what the code MEANS; `make format` then settles how it looks.
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
