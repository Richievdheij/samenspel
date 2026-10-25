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
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()->withCount('taggedEvents')->orderBy('name')->paginate(20),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = Category::query()->create(['name' => $request->string('name')->trim()->toString()]);

        return to_route('admin.categories.index')->with('status', __(':name has been added.', ['name' => $category->name]));
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', ['category' => $category]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update(['name' => $request->string('name')->trim()->toString()]);

        return to_route('admin.categories.index')->with('status', __(':name has been updated.', ['name' => $category->name]));
    }

    public function destroy(DestroyCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->delete();

        return to_route('admin.categories.index')->with('status', __(':name has been deleted.', ['name' => $category->name]));
    }
}
