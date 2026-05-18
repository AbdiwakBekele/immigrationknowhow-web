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
        if ($ad->status === 'suspended') {
            $this->publishAd($ad, $ad->meta ?? [], trackApprovalAfterSuspend: true);

            return back()->with('success', 'Suspended ad approved and published.');
        }

        abort_unless($ad->status === 'pending_approval', 422);

        $ad->update([
            'status' => 'published',
            'published_at' => $ad->published_at ?? now(),
        ]);

        return back()->with('success', 'Ad approved and published.');
    }

    public function reinstate(Ad $ad): RedirectResponse
    {
        abort_unless($ad->status === 'suspended', 422);

        $meta = is_array($ad->meta) ? $ad->meta : [];
        $previous = (string) ($meta['status_before_suspend'] ?? 'published');

        if ($previous === 'pending_approval') {
            unset($meta['suspended_at'], $meta['status_before_suspend']);
            $meta['reinstated_at'] = now()->toIso8601String();

            $ad->update([
                'status' => 'pending_approval',
                'published_at' => null,
                'meta' => $meta,
            ]);

            return back()->with('success', 'Ad reinstated and returned to the approval queue.');
        }

        $this->publishAd($ad, $meta);

        return back()->with('success', 'Ad reinstated and published.');
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

    public function suspend(Ad $ad): RedirectResponse
    {
        if ($ad->status === 'suspended') {
            return back()->with('info', 'This ad is already suspended.');
        }

        if (! in_array($ad->status, ['published', 'pending_approval'], true)) {
            return back()->with('error', 'Only published or pending-approval ads can be suspended.');
        }

        $meta = $ad->meta ?? [];
        $meta['suspended_at'] = now()->toIso8601String();
        $meta['status_before_suspend'] = $ad->status;

        $ad->update([
            'status' => 'suspended',
            'published_at' => null,
            'meta' => $meta,
        ]);

        return back()->with('success', 'Ad suspended and removed from the public site.');
    }

    public function destroy(Ad $ad): RedirectResponse
    {
        $ad->delete();

        return back()->with('success', 'Ad deleted.');
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function publishAd(Ad $ad, array $meta, bool $trackApprovalAfterSuspend = false): void
    {
        unset($meta['suspended_at'], $meta['status_before_suspend']);
        $meta['reinstated_at'] = now()->toIso8601String();
        if ($trackApprovalAfterSuspend) {
            $meta['approved_after_suspend_at'] = now()->toIso8601String();
        }

        $ad->update([
            'status' => 'published',
            'published_at' => now(),
            'meta' => $meta,
        ]);
    }

    public function preview(Ad $ad): Response
    {
        return Inertia::render('Ads/Show', [
            'ad' => [
                'uuid' => $ad->uuid,
                'title' => $ad->title,
                'description' => $ad->description,
                'cta_url' => $ad->cta_url,
                'image_url' => $ad->image_url,
                'published_at' => optional($ad->published_at)?->toIso8601String(),
                'status' => $ad->status,
            ],
            'isAdminPreview' => true,
        ]);
    }
}
