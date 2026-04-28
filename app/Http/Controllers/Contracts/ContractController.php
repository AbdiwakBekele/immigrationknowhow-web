<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Lead;
use App\Services\Contracts\ContractLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ContractController extends Controller
{
    public function __construct(
        protected ContractLifecycleService $lifecycle,
    ) {}

    public function show(Contract $contract, Request $request): JsonResponse
    {
        abort_unless($this->canAccess($contract, $request->user()), 403);

        $contract->load(['events.actor:id,first_name,last_name']);

        return response()->json([
            'contract' => $contract,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_uuid' => ['required', 'uuid'],
            'offered_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $lead = Lead::query()
            ->where('uuid', $validated['lead_uuid'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        try {
            $this->lifecycle->offer($lead, $request->user(), isset($validated['offered_rate']) ? (float) $validated['offered_rate'] : null);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Contract offer sent successfully.');
    }

    public function withdraw(Contract $contract, Request $request): RedirectResponse
    {
        abort_unless($contract->user_id === $request->user()->id, 403);

        try {
            $this->lifecycle->withdraw($contract, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Offer removed successfully.');
    }

    public function accept(Contract $contract, Request $request): RedirectResponse
    {
        abort_unless($request->user()->serviceProvider?->id === $contract->service_provider_id, 403);

        try {
            $this->lifecycle->accept($contract, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Offer accepted.');
    }

    public function end(Contract $contract, Request $request): RedirectResponse
    {
        abort_unless($contract->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->lifecycle->end($contract, $request->user(), $validated['reason'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Contract ended successfully.');
    }

    protected function canAccess(Contract $contract, $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($contract->user_id === $user->id) {
            return true;
        }

        if ($user->serviceProvider?->id === $contract->service_provider_id) {
            return true;
        }

        return $user->isAdmin();
    }
}
