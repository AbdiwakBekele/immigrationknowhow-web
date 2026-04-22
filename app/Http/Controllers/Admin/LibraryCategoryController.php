<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LibraryCategoryController extends Controller
{
    public function index(): Response
    {
        $categories = LibraryCategory::query()
            ->withCount(['items', 'activeItems as active_items_count'])
            ->ordered()
            ->get()
            ->map(fn (LibraryCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'icon' => $category->icon,
                'sort_order' => $category->sort_order,
                'is_active' => $category->is_active,
                'items_count' => (int) $category->items_count,
                'active_items_count' => (int) $category->active_items_count,
            ])
            ->values();

        return Inertia::render('Admin/LibraryCategories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        $suggestedSortOrder = (int) (LibraryCategory::max('sort_order') ?? 0) + 1;

        return Inertia::render('Admin/LibraryCategories/Create', [
            'suggested_sort_order' => $suggestedSortOrder,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('library_categories', 'name')],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        LibraryCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.library-categories.index')
            ->with('success', 'Library category created successfully.');
    }

    public function show(LibraryCategory $libraryCategory): RedirectResponse
    {
        return redirect()->route('admin.library-categories.edit', $libraryCategory);
    }

    public function edit(LibraryCategory $libraryCategory): Response
    {
        return Inertia::render('Admin/LibraryCategories/Edit', [
            'category' => [
                'id' => $libraryCategory->id,
                'name' => $libraryCategory->name,
                'slug' => $libraryCategory->slug,
                'description' => $libraryCategory->description,
                'icon' => $libraryCategory->icon,
                'sort_order' => $libraryCategory->sort_order,
                'is_active' => $libraryCategory->is_active,
                'items_count' => (int) $libraryCategory->items()->count(),
            ],
        ]);
    }

    public function update(Request $request, LibraryCategory $libraryCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('library_categories', 'name')->ignore($libraryCategory->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $libraryCategory->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()
            ->route('admin.library-categories.index')
            ->with('success', 'Library category updated successfully.');
    }

    public function destroy(LibraryCategory $libraryCategory): RedirectResponse
    {
        $libraryCategory->delete();

        return redirect()
            ->route('admin.library-categories.index')
            ->with('success', 'Library category deleted successfully.');
    }
}
