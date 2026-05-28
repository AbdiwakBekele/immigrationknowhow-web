<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryAuthor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibraryAuthorController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.library-categories.index');
    }

    public function create(): Response
    {
        return Inertia::render('Admin/LibraryAuthors/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($this->authorNameExists($validated['name'])) {
            return back()
                ->withErrors(['name' => 'An author with this name already exists.'])
                ->withInput();
        }

        LibraryAuthor::create(['name' => trim($validated['name'])]);

        return redirect()
            ->route('admin.library-categories.index')
            ->with('success', 'Library author created successfully.');
    }

    public function show(LibraryAuthor $libraryAuthor): RedirectResponse
    {
        return redirect()->route('admin.library-authors.edit', $libraryAuthor);
    }

    public function edit(LibraryAuthor $libraryAuthor): Response
    {
        return Inertia::render('Admin/LibraryAuthors/Edit', [
            'author' => [
                'id' => $libraryAuthor->id,
                'name' => $libraryAuthor->name,
                'slug' => $libraryAuthor->slug,
                'items_count' => (int) $libraryAuthor->libraryItems()->count(),
            ],
        ]);
    }

    public function update(Request $request, LibraryAuthor $libraryAuthor): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim($validated['name']);

        if ($this->authorNameExists($name, $libraryAuthor->id)) {
            return back()
                ->withErrors(['name' => 'An author with this name already exists.'])
                ->withInput();
        }

        $libraryAuthor->update(['name' => $name]);

        return redirect()
            ->route('admin.library-categories.index')
            ->with('success', 'Library author updated successfully.');
    }

    public function destroy(LibraryAuthor $libraryAuthor): RedirectResponse
    {
        $itemsCount = $libraryAuthor->libraryItems()->count();

        if ($itemsCount > 0) {
            return redirect()
                ->route('admin.library-categories.index')
                ->with('error', "Cannot delete \"{$libraryAuthor->name}\" because {$itemsCount} library item(s) use this author. Reassign those items first.");
        }

        $libraryAuthor->delete();

        return redirect()
            ->route('admin.library-categories.index')
            ->with('success', 'Library author deleted successfully.');
    }

    private function authorNameExists(string $name, ?int $ignoreId = null): bool
    {
        $query = LibraryAuthor::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))]);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
