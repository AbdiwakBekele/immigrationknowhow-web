<?php

namespace App\Http\Controllers\Provider;

use App\Actions\Affiliates\CreateAffiliateEarningAction;
use App\Enums\AffiliateCommissionTrigger;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Lead;
use App\Services\Contracts\ContractLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Logged-in providers only. Accepting a user’s contract is done by moving the lead
 * to In Progress after contract_sent_at is set; that records acceptance on the lead,
 * not as a chat message.
 */
class LeadsController extends Controller
{
    public function __construct(
        protected CreateAffiliateEarningAction $createAffiliateEarning,
        protected ContractLifecycleService $contractLifecycle,
    ) {}

    public function index(Request $request): Response
    {
        $provider = $request->user()->serviceProvider;

        $query = Lead::query()
            ->where('service_provider_id', $provider->id)
            ->with(['user:id,first_name,last_name,email,avatar', 'conversation:id,uuid,lead_id']);

        // Apply filters (open = new + contacted + in_progress; mutually exclusive with status)
        if ($request->boolean('open') && ! $request->filled('status')) {
            $query->open();
        } elseif ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('urgency')) {
            $query->where('urgency', $request->urgency);
        }

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->service_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $leads = $query->orderByDesc('created_at')->paginate(20);

        // Calculate stats
        $stats = [
            'total' => Lead::where('service_provider_id', $provider->id)->count(),
            'new' => Lead::where('service_provider_id', $provider->id)->where('status', 'new')->count(),
            'in_progress' => Lead::where('service_provider_id', $provider->id)->where('status', 'in_progress')->count(),
            'converted' => Lead::where('service_provider_id', $provider->id)->where('status', 'converted')->count(),
        ];

        $stats['conversion_rate'] = $stats['total'] > 0 
            ? round(($stats['converted'] / $stats['total']) * 100) . '%'
            : '0%';

        return Inertia::render('Provider/Leads/Index', [
            'leads' => $leads,
            'stats' => $stats,
            'filters' => $request->only(['status', 'urgency', 'search', 'service_type', 'open']),
        ]);
    }

    public function show(Lead $lead): Response
    {
        $this->authorize('view', $lead);

        $lead->load([
            'user:id,first_name,last_name,email,phone,avatar,city,state,preferred_language',
            'conversation',
        ]);

        // Get activity log (notes, status changes, etc.)
        $activityLog = collect();

        // Add lead creation
        $activityLog->push([
            'id' => 'created',
            'type' => 'lead_created',
            'description' => 'Lead received from ' . ($lead->user?->full_name ?? 'Anonymous'),
            'created_at' => $lead->created_at,
        ]);

        // Add status changes from notes/logs if you have them
        if ($lead->provider_notes) {
            $activityLog->push([
                'id' => 'provider-notes',
                'type' => 'note',
                'description' => $lead->provider_notes,
                'created_at' => $lead->updated_at,
            ]);
        }

        return Inertia::render('Provider/Leads/Show', [
            'lead' => $lead,
            'conversation' => $lead->conversation,
            'activityLog' => $activityLog->sortByDesc('created_at')->values(),
        ]);
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(LeadStatus::class)],
        ]);

        $oldStatus = $lead->status;
        $newStatus = LeadStatus::from($validated['status']);

        $updates = ['status' => $newStatus];

        if ($newStatus === LeadStatus::IN_PROGRESS) {
            if (! $lead->contract_sent_at) {
                return back()->with('error', 'User must send a contract before you can accept it.');
            }

            if (! in_array($lead->status, [LeadStatus::CONTACTED, LeadStatus::NEW], true)) {
                return back()->with('error', 'Only pending contracts can be accepted.');
            }

            $contract = $lead->contract ?: $this->contractLifecycle->ensureForLead($lead);
            $accepted = $this->contractLifecycle->accept($contract, $request->user());
            $updates['contract_accepted_at'] = $accepted->accepted_at;
            $updates['contract_agreed_rate'] = $accepted->agreed_rate;
        }

        // Update timestamps based on status
        if ($newStatus === LeadStatus::CONTACTED && ! $lead->responded_at) {
            $updates['responded_at'] = now();
        } elseif ($newStatus === LeadStatus::CONVERTED && ! $lead->converted_at) {
            $updates['converted_at'] = now();
            $updates['closed_at'] = now();
        } elseif (in_array($newStatus, [LeadStatus::CLOSED, LeadStatus::DECLINED], true) && ! $lead->closed_at) {
            $updates['closed_at'] = now();
        }

        $updates['provider_notes'] = trim(collect([
            $lead->provider_notes,
            '['.now()->toDateTimeString()."] Status changed from {$oldStatus->value} to {$newStatus->value}",
        ])->filter()->implode(PHP_EOL));

        $lead->update($updates);

        if ($newStatus === LeadStatus::CONVERTED && $lead->affiliateReferral) {
            $baseAmount = $this->estimateCommissionBaseAmount($lead->budget_range);

            $this->createAffiliateEarning->handle(
                $lead->affiliateReferral,
                AffiliateCommissionTrigger::LEAD_CONVERTED,
                Lead::class,
                $lead->id,
                $baseAmount,
                'Lead conversion commission generated from provider lead workflow.',
                ['budget_range' => $lead->budget_range],
            );

            $lead->affiliateReferral->update(['last_conversion_at' => now()]);
        }

        return back()->with('success', 'Lead status updated.');
    }

    public function addNote(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $lead->update([
            'provider_notes' => trim(collect([
                $lead->provider_notes,
                '['.now()->toDateTimeString().'] '.$validated['note'],
            ])->filter()->implode(PHP_EOL)),
        ]);

        return back()->with('success', 'Note added.');
    }

    public function createConversation(Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        // Check if conversation already exists
        if ($lead->conversation) {
            return redirect()->route('provider.messages.show', $lead->conversation);
        }

        // Create conversation
        $conversation = Conversation::create([
            'user_id' => $lead->user_id,
            'service_provider_id' => $lead->service_provider_id,
            'lead_id' => $lead->id,
        ]);

        // Update lead status if still new
        if ($lead->status === LeadStatus::NEW) {
            $lead->update([
                'status' => LeadStatus::CONTACTED,
                'responded_at' => now(),
            ]);
        }

        return redirect()->route('provider.messages.show', $conversation)
            ->with('success', 'Conversation started.');
    }

    public function decline(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $lead->update([
            'status' => LeadStatus::DECLINED,
            'closed_at' => now(),
            'provider_notes' => trim(collect([
                $lead->provider_notes,
                '['.now()->toDateTimeString().'] '.($validated['reason'] ?? 'Lead declined by provider'),
            ])->filter()->implode(PHP_EOL)),
        ]);

        return back()->with('success', 'Lead declined.');
    }

    protected function estimateCommissionBaseAmount(?string $budgetRange): float
    {
        if (! $budgetRange) {
            return 0;
        }

        preg_match_all('/\d+(?:\.\d+)?/', str_replace(',', '', $budgetRange), $matches);

        $numbers = collect($matches[0] ?? [])->map(fn ($value) => (float) $value)->filter();

        if ($numbers->isEmpty()) {
            return 0;
        }

        return round($numbers->avg(), 2);
    }
}
