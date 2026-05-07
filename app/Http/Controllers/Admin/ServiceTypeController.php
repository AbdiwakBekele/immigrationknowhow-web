<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceTypeOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceTypeController extends Controller
{
    private const DEFAULT_BACKGROUND_CHECK_KEYS = ['pet_sitter', 'babysitter', 'tutor'];

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
            'include_certificate' => ['boolean'],
            'requires_background_check' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'monthly_subscription_rate' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        if (! ($validated['for_user'] ?? false) && ! ($validated['for_provider'] ?? false)) {
            return back()->withErrors(['audience' => 'Choose at least one audience: users and/or providers.']);
        }

        $sortOrder = array_key_exists('sort_order', $validated) && $validated['sort_order'] !== null
            ? (int) $validated['sort_order']
            : (int) (ServiceTypeOption::max('sort_order') ?? 0) + 1;

        $requiresBackgroundCheck = (bool) ($validated['requires_background_check'] ?? false);
        if ($this->shouldDefaultBackgroundCheck($validated['value'] ?? null, $validated['label'] ?? null)) {
            $requiresBackgroundCheck = true;
        }

        $payload = [
            'label' => $validated['label'],
            'value' => $validated['value'],
            'icon' => $validated['icon'] ?? null,
            'for_user' => (bool) ($validated['for_user'] ?? false),
            'for_provider' => (bool) ($validated['for_provider'] ?? false),
            'include_certificate' => (bool) ($validated['include_certificate'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => $sortOrder,
            'monthly_subscription_rate' => number_format((float) $validated['monthly_subscription_rate'], 2, '.', ''),
        ];

        if ($this->hasRequiresBackgroundCheckColumn()) {
            $payload['requires_background_check'] = $requiresBackgroundCheck;
        }

        ServiceTypeOption::create($payload);

        return redirect()
            ->route('admin.service-types.index')
            ->with('success', 'Service type created successfully.');
    }

    public function edit(ServiceTypeOption $serviceType): Response
    {
        return Inertia::render('Admin/ServiceTypes/Edit', [
            'serviceType' => $serviceType,
        ]);
    }

    public function update(Request $request, ServiceTypeOption $serviceType): RedirectResponse
    {
        if ($request->input('sort_order') === '' || $request->input('sort_order') === null) {
            $request->merge(['sort_order' => null]);
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'value' => [
                'required',
                'string',
                'max:120',
                'alpha_dash',
                Rule::unique('service_type_options', 'value')->ignore($serviceType->id),
            ],
            'icon' => ['nullable', 'string', 'max:120'],
            'for_user' => ['boolean'],
            'for_provider' => ['boolean'],
            'include_certificate' => ['boolean'],
            'requires_background_check' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'monthly_subscription_rate' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        if (! ($validated['for_user'] ?? false) && ! ($validated['for_provider'] ?? false)) {
            return back()->withErrors(['audience' => 'Choose at least one audience: users and/or providers.']);
        }

        $requiresBackgroundCheck = (bool) ($validated['requires_background_check'] ?? false);
        if ($this->shouldDefaultBackgroundCheck($validated['value'] ?? null, $validated['label'] ?? null)) {
            $requiresBackgroundCheck = true;
        }

        $payload = [
            'label' => $validated['label'],
            'value' => $validated['value'],
            'icon' => $validated['icon'] ?? null,
            'for_user' => (bool) ($validated['for_user'] ?? false),
            'for_provider' => (bool) ($validated['for_provider'] ?? false),
            'include_certificate' => (bool) ($validated['include_certificate'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => array_key_exists('sort_order', $validated) ? $validated['sort_order'] : $serviceType->sort_order,
            'monthly_subscription_rate' => number_format((float) $validated['monthly_subscription_rate'], 2, '.', ''),
        ];

        if ($this->hasRequiresBackgroundCheckColumn()) {
            $payload['requires_background_check'] = $requiresBackgroundCheck;
        }

        $serviceType->update($payload);

        return redirect()
            ->route('admin.service-types.index')
            ->with('success', 'Service type updated successfully.');
    }

    public function destroy(ServiceTypeOption $serviceType): RedirectResponse
    {
        $serviceType->delete();

        return redirect()
            ->route('admin.service-types.index')
            ->with('success', 'Service type deleted successfully.');
    }

    public function toggleActive(ServiceTypeOption $serviceType): RedirectResponse
    {
        $serviceType->update([
            'is_active' => ! $serviceType->is_active,
        ]);

        return back()->with('success', 'Service type status updated.');
    }

    private function shouldDefaultBackgroundCheck(?string $value, ?string $label): bool
    {
        $normalizedValue = $this->normalizeServiceTypeKey($value);
        if (in_array($normalizedValue, self::DEFAULT_BACKGROUND_CHECK_KEYS, true)) {
            return true;
        }

        $normalizedLabel = $this->normalizeServiceTypeKey($label);
        if ($normalizedLabel === 'pet_sitter'
            || $normalizedLabel === 'babysitter'
            || str_contains($normalizedLabel, 'tutor')) {
            return true;
        }

        return false;
    }

    private function normalizeServiceTypeKey(?string $text): string
    {
        if (! is_string($text)) {
            return '';
        }

        $normalized = preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim($text))) ?? '';

        return trim($normalized, '_');
    }

    private function hasRequiresBackgroundCheckColumn(): bool
    {
        return Schema::hasTable('service_type_options')
            && Schema::hasColumn('service_type_options', 'requires_background_check');
    }
}
