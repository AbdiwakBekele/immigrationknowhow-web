<?php

namespace App\Http\Controllers\User;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Lead::query()
            ->where('user_id', $user->id)
            ->with([
                'serviceProvider:id,slug,business_name',
                'serviceProvider.user:id,first_name,last_name,email,avatar',
                'conversation' => fn ($q) => $q->select('id', 'uuid', 'lead_id')
                    ->with(['messages:id,conversation_id,sender_id']),
            ]);

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if (in_array($status, LeadStatus::values(), true)) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhereHas('serviceProvider', function ($providerQuery) use ($search) {
                        $providerQuery->where('business_name', 'like', "%{$search}%");
                    });
            });
        }

        $leads = $query
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $leads->getCollection()->transform(function (Lead $lead) use ($user) {
            $messageSenderIds = collect($lead->conversation?->messages ?? [])->pluck('sender_id')->unique()->values();
            $providerUserId = $lead->serviceProvider?->user_id;

            $hasExchangedMessages = $providerUserId
                ? $messageSenderIds->contains($user->id) && $messageSenderIds->contains($providerUserId)
                : false;

            $lead->setAttribute('has_exchanged_messages', $hasExchangedMessages);
            $lead->setAttribute(
                'can_send_contract',
                in_array($lead->status, [LeadStatus::NEW, LeadStatus::CONTACTED], true)
                    && $lead->contract_sent_at === null
            );

            return $lead;
        });

        $stats = [
            'total' => Lead::where('user_id', $user->id)->count(),
            'active' => Lead::where('user_id', $user->id)
                ->whereIn('status', [LeadStatus::CONTACTED, LeadStatus::IN_PROGRESS])
                ->count(),
            'completed' => Lead::where('user_id', $user->id)
                ->where('status', LeadStatus::CONVERTED)
                ->count(),
            'ended' => Lead::where('user_id', $user->id)
                ->whereIn('status', [LeadStatus::CLOSED, LeadStatus::DECLINED])
                ->count(),
        ];

        return Inertia::render('User/Contracts/Index', [
            'leads' => $leads,
            'stats' => $stats,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function send(Lead $lead, Request $request): RedirectResponse
    {
        if ($lead->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($lead->contract_sent_at !== null) {
            return back()->with('success', 'Contract has already been sent.');
        }

        if (! in_array($lead->status, [LeadStatus::NEW, LeadStatus::CONTACTED], true)) {
            return back()->with('error', 'Contract can only be sent from a new or contacted inquiry.');
        }

        $lead->update([
            'status' => LeadStatus::CONTACTED,
            'responded_at' => $lead->responded_at ?? now(),
            'contract_sent_at' => now(),
            'provider_notes' => trim(collect([
                $lead->provider_notes,
                '['.now()->toDateTimeString().'] Contract sent by service needer',
            ])->filter()->implode(PHP_EOL)),
        ]);

        return back()->with('success', 'Contract sent to provider. Waiting for provider acceptance.');
    }

    public function end(Lead $lead, Request $request): RedirectResponse
    {
        if ($lead->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500', Rule::notIn(['null', 'undefined'])],
        ]);

        if (in_array($lead->status, [LeadStatus::CLOSED, LeadStatus::DECLINED], true)) {
            return back()->with('success', 'This contract has already ended.');
        }

        if ($lead->status !== LeadStatus::IN_PROGRESS) {
            return back()->with('error', 'Only active contracts can be ended.');
        }

        $lead->update([
            'status' => LeadStatus::CLOSED,
            'closed_at' => now(),
            'provider_notes' => trim(collect([
                $lead->provider_notes,
                '['.now()->toDateTimeString().'] Contract ended by service needer'.(
                    ! empty($validated['reason']) ? ' - '.$validated['reason'] : ''
                ),
            ])->filter()->implode(PHP_EOL)),
        ]);

        return back()->with('success', 'Contract ended successfully.');
    }
}

