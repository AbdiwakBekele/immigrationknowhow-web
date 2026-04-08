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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'value' => ['required', 'string', 'max:120', 'alpha_dash', 'unique:service_type_options,value'],
            'icon' => ['nullable', 'string', 'max:120'],
            'for_user' => ['boolean'],
            'for_provider' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        if (!($validated['for_user'] ?? false) && !($validated['for_provider'] ?? false)) {
            return back()->withErrors(['audience' => 'Choose at least one audience: need services or provider.']);
        }

        ServiceTypeOption::create([
            'label' => $validated['label'],
            'value' => $validated['value'],
            'icon' => $validated['icon'] ?? null,
            'for_user' => (bool) ($validated['for_user'] ?? false),
            'for_provider' => (bool) ($validated['for_provider'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => (int) (ServiceTypeOption::max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('success', 'Service type created successfully.');
    }
}
