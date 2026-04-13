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
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.library.index')
            ->with('info', 'Library category management is currently handled from the library module.');
    }

    public function create(): Response
    {
        return Inertia::render('Admin/LibraryCategories/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('library_categories', 'name')],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        LibraryCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.library.index')
            ->with('success', 'Library category created successfully.');
    }

    public function show(LibraryCategory $libraryCategory): RedirectResponse
    {
        return redirect()->route('admin.library.index');
    }

    public function edit(LibraryCategory $libraryCategory): RedirectResponse
    {
        return redirect()->route('admin.library.index');
    }

    public function update(Request $request, LibraryCategory $libraryCategory): RedirectResponse
    {
        return redirect()->route('admin.library.index');
    }

    public function destroy(LibraryCategory $libraryCategory): RedirectResponse
    {
        return redirect()->route('admin.library.index');
    }
}
