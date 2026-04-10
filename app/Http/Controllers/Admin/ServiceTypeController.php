<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceTypeOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ServiceTypes/Index', [
            'serviceTypes' => ServiceTypeOption::query()
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        $nextSort = (int) (ServiceTypeOption::max('sort_order') ?? 0) + 1;

        return Inertia::render('Admin/ServiceTypes/Create', [
            'suggested_sort_order' => $nextSort,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->input('sort_order') === '' || $request->input('sort_order') === null) {
            $request->merge(['sort_order' => null]);
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'value' => ['required', 'string', 'max:120', 'alpha_dash', 'unique:service_type_options,value'],
            'icon' => ['nullable', 'string', 'max:120'],
            'for_user' => ['boolean'],
            'for_provider' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]);

        if (! ($validated['for_user'] ?? false) && ! ($validated['for_provider'] ?? false)) {
            return back()->withErrors(['audience' => 'Choose at least one audience: users and/or providers.']);
        }

        $sortOrder = array_key_exists('sort_order', $validated) && $validated['sort_order'] !== null
            ? (int) $validated['sort_order']
            : (int) (ServiceTypeOption::max('sort_order') ?? 0) + 1;

        ServiceTypeOption::create([
            'label' => $validated['label'],
            'value' => $validated['value'],
            'icon' => $validated['icon'] ?? null,
            'for_user' => (bool) ($validated['for_user'] ?? false),
            'for_provider' => (bool) ($validated['for_provider'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => $sortOrder,
        ]);

        return redirect()
            ->route('admin.service-types.index')
            ->with('success', 'Service type created successfully.');
    }
}
