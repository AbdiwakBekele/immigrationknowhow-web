<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LibraryCategoryController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.library.index')
            ->with('info', 'Library category management is currently handled from the library module.');
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.library.index');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('admin.library.index');
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
