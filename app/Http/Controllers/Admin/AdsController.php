<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdsController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Ad::query()
            ->with(['user:id,first_name,last_name,email'])
            ->withCount([
                'analyticsEvents as views_count' => fn ($q) => $q->where('event_type', 'view'),
                'analyticsEvents as clicks_count' => fn ($q) => $q->where('event_type', 'click'),
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhereHas('user', function ($uq) use ($term) {
                        $uq->where('email', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term);
                    });
            });
        }

        $ads = $query->latest()->paginate(20)->withQueryString();

        $pendingApprovalCount = Ad::query()->where('status', 'pending_approval')->count();

        return Inertia::render('Admin/Ads/Index', [
            'ads' => $ads,
            'filters' => [
                'status' => (string) $request->input('status', ''),
                'search' => (string) $request->input('search', ''),
            ],
            'pendingApprovalCount' => $pendingApprovalCount,
            'requireAdminApproval' => (bool) config('ads.require_admin_approval', true),
        ]);
    }

    public function approve(Ad $ad): RedirectResponse
    {
        abort_unless($ad->status === 'pending_approval', 422);

        $ad->update([
            'status' => 'published',
            'published_at' => $ad->published_at ?? now(),
        ]);

        return back()->with('success', 'Ad approved and published.');
    }

    public function reject(Request $request, Ad $ad): RedirectResponse
    {
        abort_unless($ad->status === 'pending_approval', 422);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $meta = $ad->meta ?? [];
        $meta['rejection_reason'] = $validated['reason'] ?? null;
        $meta['rejected_at'] = now()->toIso8601String();

        $ad->update([
            'status' => 'rejected',
            'published_at' => null,
            'meta' => $meta,
        ]);

        return back()->with('success', 'Ad rejected.');
    }
}
