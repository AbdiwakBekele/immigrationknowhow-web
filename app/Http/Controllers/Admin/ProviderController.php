<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Support\ServiceTypeOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ServiceProvider::query()
            ->with('user:id,first_name,last_name,email,avatar')
            ->withCount(['leads', 'reviews']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by service type
        if ($request->filled('service_type')) {
            $query->whereJsonContains('service_types', $request->service_type);
        }

        // Filter by verification status
        if ($request->filled('verified')) {
            $query->where('is_verified', $request->verified === 'yes');
        }

        // Filter by active status
        if ($request->filled('active')) {
            $query->where('is_active', $request->active === 'yes');
        }

        // Sort
        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $providers = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Providers/Index', [
            'providers' => $providers,
            'filters' => $request->only(['search', 'service_type', 'verified', 'active', 'sort', 'dir']),
            'serviceTypes' => ServiceTypeOptions::selectOptions(),
            'stats' => [
                'total' => ServiceProvider::query()->count(),
                'verified' => ServiceProvider::query()->where('is_verified', true)->count(),
                'active' => ServiceProvider::query()->where('is_active', true)->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Providers/Create', [
            'serviceTypes' => collect(ServiceType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', Password::defaults()],
            'email_verified' => ['boolean'],

            'business_name' => ['required', 'string', 'max:255'],
            'primary_service_type' => ['required', Rule::enum(ServiceType::class)],
            'tagline' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:500'],

            'pricing_model' => ['nullable', 'string', 'max:50'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'consultation_fee' => ['nullable', 'numeric', 'min:0'],
            'free_consultation' => ['boolean'],
            'pricing_notes' => ['nullable', 'string', 'max:1000'],

            'serves_remote' => ['boolean'],
            'serves_in_person' => ['boolean'],
            'service_radius_miles' => ['nullable', 'integer', 'min:0'],

            'license_number' => ['nullable', 'string', 'max:100'],
            'license_state' => ['nullable', 'string', 'max:10'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],

            'linkedin_url' => ['nullable', 'string', 'max:500'],

            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'accepting_clients' => ['boolean'],
            'is_verified' => ['boolean'],
        ]);

        foreach (['hourly_rate', 'consultation_fee', 'service_radius_miles', 'years_experience', 'pricing_model', 'website', 'linkedin_url'] as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] === '') {
                $validated[$key] = null;
            }
        }

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'email_verified_at' => ($validated['email_verified'] ?? false) ? now() : null,
                'onboarding_completed' => true,
                'onboarding_completed_at' => now(),
            ]);

            $user->assignRole(UserRole::PROVIDER->value);

            $isVerified = (bool) ($validated['is_verified'] ?? false);

            ServiceProvider::create([
                'user_id' => $user->id,
                'business_name' => $validated['business_name'],
                'bio' => $validated['bio'] ?? null,
                'description' => $validated['description'] ?? null,
                'tagline' => $validated['tagline'] ?? null,
                'business_email' => $validated['business_email'] ?? $validated['email'],
                'business_phone' => $validated['business_phone'] ?? $validated['phone'] ?? null,
                'website' => $validated['website'] ?? null,
                'service_types' => [$validated['primary_service_type']],
                'languages_offered' => ['en'],
                'pricing_model' => $validated['pricing_model'] ?? null,
                'hourly_rate' => $validated['hourly_rate'] ?? null,
                'consultation_fee' => $validated['consultation_fee'] ?? null,
                'free_consultation' => (bool) ($validated['free_consultation'] ?? false),
                'pricing_notes' => $validated['pricing_notes'] ?? null,
                'serves_remote' => (bool) ($validated['serves_remote'] ?? false),
                'serves_in_person' => (bool) ($validated['serves_in_person'] ?? true),
                'service_radius_miles' => $validated['service_radius_miles'] ?? null,
                'license_number' => $validated['license_number'] ?? null,
                'license_state' => $validated['license_state'] ?? null,
                'years_experience' => $validated['years_experience'] ?? null,
                'linkedin_url' => $validated['linkedin_url'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'is_featured' => (bool) ($validated['is_featured'] ?? false),
                'accepting_clients' => (bool) ($validated['accepting_clients'] ?? true),
                'is_verified' => $isVerified,
                'verified_at' => $isVerified ? now() : null,
                'verification_status' => $isVerified ? VerificationStatus::APPROVED : VerificationStatus::PENDING,
            ]);
        });

        return redirect()->route('admin.providers.index')
            ->with('success', 'Service provider created successfully.');
    }

    public function show(ServiceProvider $provider): Response
    {
        $provider->load([
            'user:id,first_name,last_name,email,phone,avatar,created_at',
            'reviews' => fn ($q) => $q->latest()->limit(5)->with('user:id,first_name,last_name'),
            'backgroundChecks' => fn ($q) => $q->latest(),
        ]);

        return Inertia::render('Admin/Providers/Show', [
            'provider' => $provider,
            'stats' => [
                'leads_count' => $provider->leads()->count(),
                'leads_converted' => $provider->leads()->where('status', 'converted')->count(),
                'reviews_count' => $provider->reviews_count,
                'average_rating' => $provider->average_rating,
            ],
        ]);
    }

    public function edit(ServiceProvider $provider): Response
    {
        return Inertia::render('Admin/Providers/Edit', [
            'provider' => $provider->load('user'),
            'serviceTypes' => collect(ServiceType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
        ]);
    }

    public function update(Request $request, ServiceProvider $provider): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'primary_service_type' => ['required', Rule::enum(ServiceType::class)],
            'bio' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'is_verified' => ['boolean'],
        ]);

        // If verification status is being changed manually
        if ($validated['is_verified'] !== $provider->is_verified) {
            $validated['verified_at'] = $validated['is_verified'] ? now() : null;
        }

        // The DB stores service types as JSON array (service_types), not primary_service_type column.
        $validated['service_types'] = [$validated['primary_service_type']];
        unset($validated['primary_service_type']);

        $provider->update($validated);

        return redirect()->route('admin.providers.index')
            ->with('success', 'Provider updated successfully.');
    }

    public function destroy(ServiceProvider $provider): RedirectResponse
    {
        // Soft delete the provider
        $provider->update(['is_active' => false]);
        $provider->delete();

        return redirect()->route('admin.providers.index')
            ->with('success', 'Provider has been deactivated.');
    }

    public function verify(ServiceProvider $provider): RedirectResponse
    {
        $provider->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        // TODO: Send notification to provider

        return back()->with('success', 'Provider has been verified.');
    }

    public function unverify(ServiceProvider $provider): RedirectResponse
    {
        $provider->update([
            'is_verified' => false,
            'verified_at' => null,
        ]);

        return back()->with('success', 'Provider verification has been revoked.');
    }

    public function toggleFeatured(ServiceProvider $provider): RedirectResponse
    {
        $provider->update(['is_featured' => ! $provider->is_featured]);

        return back()->with('success', $provider->is_featured
            ? 'Provider is now featured.'
            : 'Provider is no longer featured.');
    }

    public function activate(ServiceProvider $provider): RedirectResponse
    {
        $provider->update(['is_active' => true]);
        $provider->restore(); // If soft deleted

        return back()->with('success', 'Provider has been activated.');
    }

    public function deactivate(ServiceProvider $provider): RedirectResponse
    {
        $provider->update(['is_active' => false]);

        return back()->with('success', 'Provider has been deactivated.');
    }

    public function exportCsv(Request $request)
    {
        $providers = ServiceProvider::query()
            ->with('user:id,first_name,last_name,email')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="providers-'.now()->format('Y-m-d').'.csv"',
        ];

        $callback = function () use ($providers) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'ID', 'Business Name', 'Owner Name', 'Email', 'Service Type',
                'Verified', 'Active', 'Rating', 'Reviews', 'Created At',
            ]);

            foreach ($providers as $p) {
                $serviceType = $p->service_types[0] ?? null;
                fputcsv($file, [
                    $p->id,
                    $p->business_name,
                    $p->user?->full_name,
                    $p->user?->email,
                    ServiceType::tryFrom($serviceType)?->label() ?? $serviceType,
                    $p->is_verified ? 'Yes' : 'No',
                    $p->is_active ? 'Yes' : 'No',
                    $p->average_rating,
                    $p->reviews_count,
                    $p->created_at->format('Y-m-d'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
