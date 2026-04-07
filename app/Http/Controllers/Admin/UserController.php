<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()
            ->with('roles:id,name')
            ->withCount(['leads', 'reviews']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $request->role));
        }

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($request->status === 'unverified') {
                $query->whereNull('email_verified_at');
            }
        }

        // Sort
        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $users = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'status', 'sort', 'dir']),
            'roles' => collect(UserRole::cases())->map(fn($r) => ['value' => $r->value, 'label' => $r->label()]),
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
            'roles' => collect(UserRole::cases())->map(fn($r) => ['value' => $r->value, 'label' => $r->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'email_verified' => ['boolean'],
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => $validated['email_verified'] ? now() : null,
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->load('roles'),
            'roles' => collect(UserRole::cases())->map(fn($r) => ['value' => $r->value, 'label' => $r->label()]),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'email_verified' => ['boolean'],
        ]);

        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'email_verified_at' => $validated['email_verified'] ? ($user->email_verified_at ?? now()) : null,
        ]);

        $user->syncRoles([$validated['role']]);

        return back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        // Prevent deleting super admins (unless you're a super admin)
        if ($user->hasRole('super_admin') && !auth()->user()->hasRole('super_admin')) {
            return back()->withErrors(['error' => 'You cannot delete a super admin.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function impersonate(User $user): RedirectResponse
    {
        // Only super admins can impersonate
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403);
        }

        // Can't impersonate self
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot impersonate yourself.']);
        }

        session()->put('impersonating', auth()->id());
        auth()->login($user);

        return redirect('/dashboard')
            ->with('info', "You are now impersonating {$user->full_name}.");
    }

    public function stopImpersonating(): RedirectResponse
    {
        $originalUserId = session()->pull('impersonating');

        if ($originalUserId) {
            auth()->loginUsingId($originalUserId);
        }

        return redirect()->route('admin.dashboard')
            ->with('success', 'Stopped impersonating.');
    }

    public function ban(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot ban yourself.']);
        }

        $user->update(['banned_at' => now()]);

        return back()->with('success', 'User has been banned.');
    }

    public function unban(User $user): RedirectResponse
    {
        $user->update(['banned_at' => null]);

        return back()->with('success', 'User has been unbanned.');
    }
}
