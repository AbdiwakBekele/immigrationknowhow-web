<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        $user = auth()->user();

        return Inertia::render('User/Profile/Edit', [
            'user' => array_merge(
                $user->only([
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                    'avatar',
                    'city',
                    'state',
                    'country',
                    'preferred_language',
                    'languages_spoken',
                    'immigration_status',
                    'country_of_origin',
                    'notification_preferences',
                ]),
                ['avatar_url' => $user->avatar_url]
            ),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'preferred_language' => ['nullable', 'string', 'max:50'],
            'languages_spoken' => ['nullable', 'array'],
            'languages_spoken.*' => ['string', 'max:50'],
            'immigration_status' => ['nullable', 'string', 'max:100'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
        ]);

        $user->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
        ]);

        $user = auth()->user();

        // Delete old avatar if exists
        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return back()->with('success', 'Avatar updated successfully.');
    }

    public function deleteAvatar(): RedirectResponse
    {
        $user = auth()->user();

        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => null]);

        return back()->with('success', 'Avatar removed.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        auth()->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notification_preferences' => ['required', 'array'],
            'notification_preferences.email_new_message' => ['boolean'],
            'notification_preferences.email_lead_update' => ['boolean'],
            'notification_preferences.email_review_received' => ['boolean'],
            'notification_preferences.email_marketing' => ['boolean'],
            'notification_preferences.push_enabled' => ['boolean'],
        ]);

        auth()->user()->update([
            'notification_preferences' => $validated['notification_preferences'],
        ]);

        return back()->with('success', 'Notification preferences updated.');
    }

    public function deleteAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', 'in:DELETE'],
        ]);

        $user = auth()->user();

        // Delete avatar
        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Log out
        auth()->logout();

        // Soft delete the user
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Your account has been deleted.');
    }
}
