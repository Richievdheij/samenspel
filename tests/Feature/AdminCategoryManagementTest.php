<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Event;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('keeps category management for signed-in admins only', function (): void {
    $category = Category::factory()->create(['name' => 'LAN']);
    $user = User::factory()->create();

    get(route('admin.categories.index'))->assertRedirect(route('login'));
    actingAs($user)->get(route('admin.categories.index'))->assertForbidden();
    actingAs($user)->post(route('admin.categories.store'), ['name' => 'Speedrun'])->assertForbidden();
    actingAs($user)->get(route('admin.categories.edit', $category))->assertForbidden();
    actingAs($user)->put(route('admin.categories.update', $category), ['name' => 'Taken over'])->assertForbidden();
    actingAs($user)->delete(route('admin.categories.destroy', $category))->assertForbidden();
    actingAs($user)->get(route('dashboard'))->assertDontSeeHtml('href="'.route('admin.categories.index').'"');

    expect(Category::query()->pluck('name')->all())->toBe(['LAN']);
});

it('lists the categories with how many events use them, and links the admin there', function (): void {
    $admin = User::factory()->admin()->create();
    $lan = Category::factory()->create(['name' => 'LAN']);
    Event::factory()->count(2)->for($lan)->create();
    Category::factory()->create(['name' => 'Online']);

    actingAs($admin)->get(route('dashboard'))->assertSeeHtml('href="'.route('admin.categories.index').'"');
    actingAs($admin)->get(route('admin.categories.index'))
        ->assertOk()
        ->assertSeeInOrder(['LAN', '2 events', 'Online', '0 events']);
});

it('adds a category with a unique name', function (): void {
    $admin = User::factory()->admin()->create();
    Category::factory()->create(['name' => 'LAN']);

    actingAs($admin)->post(route('admin.categories.store'), ['name' => '  Speedrun  '])
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHas('status', 'Speedrun is toegevoegd.');
    actingAs($admin)->post(route('admin.categories.store'), ['name' => 'LAN'])
        ->assertSessionHasErrors(['name' => 'Er bestaat al een categorie met deze naam.']);
    actingAs($admin)->post(route('admin.categories.store'), ['name' => ''])->assertSessionHasErrors('name');

    expect(Category::query()->orderBy('name')->pluck('name')->all())->toBe(['LAN', 'Speedrun']);
});

it('renames a category, keeping its own name allowed and others refused', function (): void {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['name' => 'Lan']);
    Category::factory()->create(['name' => 'Online']);

    actingAs($admin)->get(route('admin.categories.edit', $category))->assertOk()->assertSeeHtml('value="Lan"');
    actingAs($admin)->put(route('admin.categories.update', $category), ['name' => 'Lan'])->assertSessionHasNoErrors();
    actingAs($admin)->put(route('admin.categories.update', $category), ['name' => 'Online'])->assertSessionHasErrors('name');
    actingAs($admin)->put(route('admin.categories.update', $category), ['name' => 'LAN-party'])
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHas('status', 'LAN-party is bijgewerkt.');

    expect($category->refresh()->name)->toBe('LAN-party');
});

it('deletes an unused category and refuses one that events use', function (): void {
    $admin = User::factory()->admin()->create();
    $unused = Category::factory()->create();
    $used = Category::factory()->create();
    $tagged = Category::factory()->create();
    Event::factory()->for($used)->create();
    Event::factory()->create()->categories()->attach($tagged);

    actingAs($admin)->delete(route('admin.categories.destroy', $unused))
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHasNoErrors();
    actingAs($admin)->delete(route('admin.categories.destroy', $used))
        ->assertSessionHasErrors(['category' => 'Deze categorie wordt gebruikt door 1 event en kan niet worden verwijderd.']);
    actingAs($admin)->delete(route('admin.categories.destroy', $tagged))->assertSessionHasErrors('category');

    expect(Category::query()->find($unused->id))->toBeNull()
        ->and(Category::query()->find($used->id))->not->toBeNull()
        ->and(Category::query()->find($tagged->id))->not->toBeNull();
});
