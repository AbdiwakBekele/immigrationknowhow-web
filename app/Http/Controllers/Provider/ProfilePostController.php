<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderProfilePost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfilePostController extends Controller
{
    protected const MAX_POSTS = 100;

    public function store(Request $request): RedirectResponse
    {
        $provider = $request->user()->serviceProvider;
        abort_unless($provider, 404);

        if ($provider->profilePosts()->count() >= self::MAX_POSTS) {
            return back()->withErrors(['feed' => 'You have reached the maximum number of profile posts. Remove some to add more.']);
        }

        $validated = $request->validate([
            'type' => ['required', 'in:video,article'],
            'url' => ['required', 'url', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ]);

        $provider->profilePosts()->create([
            'type' => $validated['type'],
            'url' => $validated['url'],
            'title' => $validated['title'] ?? null,
            'caption' => $validated['caption'] ?? null,
        ]);

        return back()->with('success', 'Added to your public profile feed.');
    }

    public function update(Request $request, ProviderProfilePost $profilePost): RedirectResponse
    {
        $provider = $request->user()->serviceProvider;
        abort_unless($provider && (int) $profilePost->service_provider_id === (int) $provider->id, 403);

        $validated = $request->validate([
            'type' => ['required', 'in:video,article'],
            'url' => ['required', 'url', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ]);

        $profilePost->update([
            'type' => $validated['type'],
            'url' => $validated['url'],
            'title' => $validated['title'] ?? null,
            'caption' => $validated['caption'] ?? null,
        ]);

        return back()->with('success', 'Profile feed item updated.');
    }

    public function destroy(Request $request, ProviderProfilePost $profilePost): RedirectResponse
    {
        $provider = $request->user()->serviceProvider;
        abort_unless($provider && (int) $profilePost->service_provider_id === (int) $provider->id, 403);

        $profilePost->delete();

        return back()->with('success', 'Removed from your profile feed.');
    }
}
