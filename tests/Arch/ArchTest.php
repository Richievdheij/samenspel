<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

/*
 * Architecture rules: the conventions a compiler cannot see and a code review
 * keeps re-litigating.
 *
 * Three rules you wrote yourself, for a mistake you have actually seen, are worth
 * more than forty-five inherited from another repository — so this file is short
 * and every rule here is one this layout genuinely relies on. When a rule and the
 * code disagree, decide which is wrong; do not add an exception and move on.
 */

arch('app code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('models live in App\Models and extend Eloquent')
    ->expect('App\Models')
    ->toBeClasses()
    ->toExtend(Model::class);

arch('controllers are named Controller')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('form requests are named Request and extend FormRequest')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request')
    ->toExtend(FormRequest::class);

arch('policies are named Policy')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy');

arch('resources are named Resource')
    ->expect('App\Http\Resources')
    ->toHaveSuffix('Resource');

arch('controllers do not run queries directly')
    // A raw DB call in a controller is how query logic ends up duplicated across
    // three actions and wrong in two of them. It belongs on the model or in an
    // action class.
    ->expect(DB::class)
    ->not->toBeUsedIn('App\Http\Controllers');

arch('models do not depend on HTTP')
    // A model that knows about a Request cannot be used by a console command, a
    // queued job or a seeder without one.
    ->expect('App\Models')
    ->not->toUse('Illuminate\Http\Request');

arch('no debugging helper is ever committed')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray', 'exit'])
    ->not->toBeUsed();

// Pest's own presets: `php` forbids the language's genuinely dangerous calls
// (eval, extract, compact-by-string), `security` the weak hashes and unsafe
// randomness. Both are maintained upstream, so they cost nothing to keep.
arch()->preset()->php();
arch()->preset()->security();
