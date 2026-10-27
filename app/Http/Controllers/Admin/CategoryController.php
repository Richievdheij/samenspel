<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyCategoryRequest;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Category management for admins, with the same rules as the games. */
final class CategoryController extends Controller
{
    /** Every category with the number of events that use it, twenty to a page. */
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()->withCount('taggedEvents')->orderBy('name')->paginate(20),
        ]);
    }

    /** Add a category under the trimmed name the request has validated. */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = Category::query()->create(['name' => $request->string('name')->trim()->toString()]);

        return to_route('admin.categories.index')->with('status', __(':name has been added.', ['name' => $category->name]));
    }

    /** The rename form; a category has nothing to change but its name. */
    public function edit(Category $category): View
    {
        return view('admin.categories.edit', ['category' => $category]);
    }

    /** Rename the category; the events that use it show the new name at once. */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update(['name' => $request->string('name')->trim()->toString()]);

        return to_route('admin.categories.index')->with('status', __(':name has been updated.', ['name' => $category->name]));
    }

    /** Delete the category; the request has refused it while an event still uses it. */
    public function destroy(DestroyCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->delete();

        return to_route('admin.categories.index')->with('status', __(':name has been deleted.', ['name' => $category->name]));
    }
}
