<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Affiliates\CreateAffiliateEarningAction;
use App\Enums\AffiliateCommissionTrigger;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\LeadResource;
use App\Models\Lead;
use App\Services\Contracts\ContractLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProviderLeadsController extends Controller
{
    public function __construct(
        protected CreateAffiliateEarningAction $createAffiliateEarning,
        protected ContractLifecycleService $contractLifecycle,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $provider = $request->user()->serviceProvider;
        if (! $provider) {
            return $this->error('You must be a service provider to view leads.', [], 403);
        }

        $query = Lead::query()
            ->where('service_provider_id', $provider->id)
            ->with(['user:id,first_name,last_name,email,avatar', 'conversation:id,uuid,lead_id']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('urgency')) {
            $query->where('urgency', $request->urgency);
        }

        if ($request->filled('search')) {
            $search = (string) $request->search;
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

        $stats = [
            'total' => Lead::where('service_provider_id', $provider->id)->count(),
            'new' => Lead::where('service_provider_id', $provider->id)->where('status', LeadStatus::NEW)->count(),
            'in_progress' => Lead::where('service_provider_id', $provider->id)->where('status', LeadStatus::IN_PROGRESS)->count(),
            'converted' => Lead::where('service_provider_id', $provider->id)->where('status', LeadStatus::CONVERTED)->count(),
        ];

        $stats['conversion_rate'] = $stats['total'] > 0
            ? round(($stats['converted'] / $stats['total']) * 100).'%'
            : '0%';

        return $this->success('OK', [
            'leads' => LeadResource::collection($leads)->response()->getData(true),
            'stats' => $stats,
            'filters' => $request->only(['status', 'urgency', 'search']),
        ]);
    }

    public function show(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        $provider = $request->user()->serviceProvider;
        if (! $provider || (int) $lead->service_provider_id !== (int) $provider->id) {
            return $this->error('Forbidden', [], 403);
        }

        if (! $lead->viewed_at) {
            $lead->markAsViewed();
        }

        $lead->load([
            'user:id,first_name,last_name,email,phone,avatar,city,state,preferred_language',
            'conversation:id,uuid,lead_id',
            'contract:id,uuid,lead_id,state,offered_rate,agreed_rate,offered_at,accepted_at',
        ]);

        $activityLog = collect();
        $activityLog->push([
            'id' => 'created',
            'type' => 'lead_created',
            'description' => 'Lead received from '.($lead->user?->full_name ?? 'Anonymous'),
            'created_at' => optional($lead->created_at)->toIso8601String(),
        ]);
        if ($lead->provider_notes) {
            $activityLog->push([
                'id' => 'provider-notes',
                'type' => 'note',
                'description' => $lead->provider_notes,
                'created_at' => optional($lead->updated_at)->toIso8601String(),
            ]);
        }

        return $this->success('OK', [
            'lead' => (new LeadResource($lead))->resolve(),
            'activity_log' => $activityLog->sortByDesc('created_at')->values()->all(),
        ]);
    }

    public function updateStatus(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $provider = $request->user()->serviceProvider;
        if (! $provider || (int) $lead->service_provider_id !== (int) $provider->id) {
            return $this->error('Forbidden', [], 403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::enum(LeadStatus::class)],
        ]);

        $oldStatus = $lead->status;
        $newStatus = LeadStatus::from($validated['status']);

        $updates = ['status' => $newStatus];

        if ($newStatus === LeadStatus::IN_PROGRESS) {
            if (! $lead->contract_sent_at) {
                return $this->error('User must send a contract before you can accept it.', [], 422);
            }

            if (! in_array($lead->status, [LeadStatus::CONTACTED, LeadStatus::NEW], true)) {
                return $this->error('Only pending contracts can be accepted.', [], 422);
            }

            $contract = $lead->contract ?: $this->contractLifecycle->ensureForLead($lead);
            $accepted = $this->contractLifecycle->accept($contract, $request->user());
            $updates['contract_accepted_at'] = $accepted->accepted_at;
            $updates['contract_agreed_rate'] = $accepted->agreed_rate;
            $updates['status'] = LeadStatus::IN_PROGRESS;
        }

        $lead->refresh();

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

        return $this->success('Lead status updated.', [
            'lead' => (new LeadResource($lead->refresh()))->resolve(),
        ]);
    }

    public function addNote(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $provider = $request->user()->serviceProvider;
        if (! $provider || (int) $lead->service_provider_id !== (int) $provider->id) {
            return $this->error('Forbidden', [], 403);
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $lead->update([
            'provider_notes' => trim(collect([
                $lead->provider_notes,
                '['.now()->toDateTimeString().'] '.$validated['note'],
            ])->filter()->implode(PHP_EOL)),
        ]);

        return $this->success('Note added.', [
            'lead' => (new LeadResource($lead->refresh()))->resolve(),
        ]);
    }

    public function createConversation(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $provider = $request->user()->serviceProvider;
        if (! $provider || (int) $lead->service_provider_id !== (int) $provider->id) {
            return $this->error('Forbidden', [], 403);
        }

        if ($lead->conversation) {
            return $this->success('Conversation already exists.', [
                'conversation_uuid' => $lead->conversation->uuid,
            ]);
        }

        $conversation = $lead->createConversation();

        if ($lead->status === LeadStatus::NEW) {
            $lead->update([
                'status' => LeadStatus::CONTACTED,
                'responded_at' => now(),
            ]);
        }

        return $this->success('Conversation started.', [
            'conversation_uuid' => $conversation->uuid,
        ]);
    }

    public function decline(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $provider = $request->user()->serviceProvider;
        if (! $provider || (int) $lead->service_provider_id !== (int) $provider->id) {
            return $this->error('Forbidden', [], 403);
        }

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

        return $this->success('Lead declined.', [
            'lead' => (new LeadResource($lead->refresh()))->resolve(),
        ]);
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function success(string $message, array $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    private function error(string $message, array $errors = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors === [] ? (object) [] : $errors,
        ], $status);
    }
}
