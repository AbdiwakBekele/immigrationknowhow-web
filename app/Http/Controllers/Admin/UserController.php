<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BackgroundCheckStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function checkEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = trim(strtolower($request->string('email')->toString()));
        $exists = User::whereRaw('LOWER(email) = ?', [$email])->exists();

        return response()->json([
            'available' => ! $exists,
        ]);
    }

    public function index(Request $request): Response
    {
        $query = User::query()
            ->with([
                'roles:id,name',
                'serviceProvider:id,user_id,background_check_status,background_check_verified_at',
            ])
            ->withCount(['leads', 'reviews']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->role));
        }

        if ($request->filled('status')) {
            if ($request->status === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($request->status === 'unverified') {
                $query->whereNull('email_verified_at');
            }
        }

        if ($request->filled('background_check')) {
            $value = $request->string('background_check')->toString();

            if (in_array($value, BackgroundCheckStatus::values(), true)) {
                $query->whereHas('serviceProvider', function ($providerQuery) use ($value) {
                    $providerQuery->where('background_check_status', $value);
                });
            } elseif ($value === 'none') {
                $query->whereDoesntHave('serviceProvider', function ($providerQuery) {
                    $providerQuery->whereNotNull('background_check_status');
                });
            }
        }

        $allowedSorts = ['created_at', 'first_name', 'email', 'last_login_at'];
        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');

        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $query->orderBy($sortBy, $sortDir);

        $users = $query->paginate(20)->withQueryString();

        $selectedUser = null;

        if ($request->filled('view')) {
            $selectedUser = User::query()
                ->with(['roles:id,name', 'serviceProvider', 'leads', 'reviews'])
                ->withCount(['leads', 'reviews'])
                ->find($request->integer('view'));

            if ($selectedUser) {
                $selectedUser->setAttribute('role_name', optional($selectedUser->roles->first())->name);
            }
        }

        $stats = [
            'total' => User::count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
            'new_today' => User::where('created_at', '>=', now()->startOfDay())->count(),
        ];

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'stats' => $stats,
            'filters' => $request->only(['search', 'role', 'status', 'background_check', 'sort', 'dir', 'view']),
            'roles' => collect(UserRole::cases())->map(fn ($r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ]),
            'backgroundCheckStatuses' => collect(BackgroundCheckStatus::cases())->map(fn ($status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values(),
        ]);
    }

    public function show(User $user): Response
    {
        $user->load(['roles', 'serviceProvider', 'leads', 'reviews']);

        return Inertia::render('Admin/Users/Show', [
            'user' => $user,
            'activity' => [
                'leads_count' => $user->leads->count(),
                'reviews_count' => $user->reviews->count(),
                'last_login' => $user->last_login_at?->format('M d, Y H:i'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => collect(UserRole::cases())->map(fn ($r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'preferred_language' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'email_verified' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country' => $validated['country'] ?? null,
            'preferred_language' => $validated['preferred_language'] ?? 'en',
            'timezone' => $validated['timezone'] ?? 'America/New_York',
            'email_verified_at' => ($validated['email_verified'] ?? false) ? now() : null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->load('roles'),
            'roles' => collect(UserRole::cases())->map(fn ($r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ]),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'preferred_language' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'email_verified' => ['boolean'],
            'is_active' => ['boolean'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
                Storage::disk('public')->delete($user->avatar);
            }

            $validated['avatar'] = $request->file('avatar')->store('avatars/users', 'public');
        }

        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country' => $validated['country'] ?? null,
            'preferred_language' => $validated['preferred_language'] ?? 'en',
            'timezone' => $validated['timezone'] ?? 'America/New_York',
            'email_verified_at' => ($validated['email_verified'] ?? false)
                ? ($user->email_verified_at ?? now())
                : null,
            'is_active' => $validated['is_active'] ?? true,
            'avatar' => $validated['avatar'] ?? $user->avatar,
        ]);

        $user->syncRoles([$validated['role']]);

        return back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        if ($user->hasRole('super_admin') && ! auth()->user()->hasRole('super_admin')) {
            return back()->withErrors(['error' => 'You cannot delete a super admin.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function impersonate(User $user): RedirectResponse
    {
        $actor = auth()->user();

        if (! $actor->isAdmin()) {
            abort(403);
        }

        if (session()->has('impersonating')) {
            return back()->withErrors(['error' => 'You are already viewing as another user. Exit impersonation first.']);
        }

        if ($user->id === $actor->id) {
            return back()->withErrors(['error' => 'You cannot impersonate yourself.']);
        }

        if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return back()->withErrors(['error' => 'Only a super admin can switch into a super admin account.']);
        }

        session()->put('impersonating', [
            'id' => $actor->id,
            'name' => $actor->full_name,
        ]);
        auth()->login($user);

        return redirect()->to($user->defaultAuthenticatedHomeUrl())
            ->with('info', "You are now viewing the site as {$user->full_name}.");
    }

    public function stopImpersonating(): RedirectResponse
    {
        $payload = session()->pull('impersonating');
        $originalUserId = is_array($payload) ? ($payload['id'] ?? null) : $payload;

        if ($originalUserId) {
            $original = User::find($originalUserId);
            if ($original) {
                auth()->login($original);

                return redirect()->route('admin.dashboard')
                    ->with('success', 'You are signed back in as your admin account.');
            }
        }

        return redirect()->route('login')
            ->with('warning', 'Your admin session could not be restored. Please sign in again.');
    }
}
