<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            $query->where('primary_service_type', $request->service_type);
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
            'serviceTypes' => collect(ServiceType::cases())->map(fn($t) => ['value' => $t->value, 'label' => $t->label()]),
        ]);
    }

    public function show(ServiceProvider $provider): Response
    {
        $provider->load([
            'user:id,first_name,last_name,email,phone,avatar,created_at',
            'reviews' => fn($q) => $q->latest()->limit(5)->with('user:id,first_name,last_name'),
            'identityVerifications' => fn($q) => $q->latest(),
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
            'serviceTypes' => collect(ServiceType::cases())->map(fn($t) => ['value' => $t->value, 'label' => $t->label()]),
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
            $validated['verified_by'] = $validated['is_verified'] ? auth()->id() : null;
        }

        $provider->update($validated);

        return back()->with('success', 'Provider updated successfully.');
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
            'verified_by' => auth()->id(),
        ]);

        // TODO: Send notification to provider

        return back()->with('success', 'Provider has been verified.');
    }

    public function unverify(ServiceProvider $provider): RedirectResponse
    {
        $provider->update([
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        return back()->with('success', 'Provider verification has been revoked.');
    }

    public function toggleFeatured(ServiceProvider $provider): RedirectResponse
    {
        $provider->update(['is_featured' => !$provider->is_featured]);

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
            'Content-Disposition' => 'attachment; filename="providers-' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($providers) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'ID', 'Business Name', 'Owner Name', 'Email', 'Service Type',
                'Verified', 'Active', 'Rating', 'Reviews', 'Created At'
            ]);

            foreach ($providers as $p) {
                fputcsv($file, [
                    $p->id,
                    $p->business_name,
                    $p->user?->full_name,
                    $p->user?->email,
                    $p->primary_service_type?->value,
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
