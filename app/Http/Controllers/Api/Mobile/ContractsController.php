<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\ContractResource;
use App\Models\Contract;
use App\Models\Lead;
use App\Services\Contracts\ContractLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractsController extends Controller
{
    public function __construct(
        protected ContractLifecycleService $lifecycle,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Contract::query()
            ->with([
                'lead:id,uuid,user_id,service_provider_id,service_type,status,message,contract_sent_at,contract_accepted_at',
                'serviceProvider:id,slug,business_name',
            ])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->serviceProvider) {
                    $q->orWhere('service_provider_id', $user->serviceProvider->id);
                }
            })
            ->latest('id');

        return $this->success('OK', [
            'contracts' => ContractResource::collection($query->paginate(20))->response()->getData(true),
        ]);
    }

    public function show(Request $request, Contract $contract): JsonResponse
    {
        abort_unless($this->canAccess($contract, $request->user()), 403);

        $contract->load([
            'lead:id,uuid,user_id,service_provider_id,service_type,status,message,contract_sent_at,contract_accepted_at',
            'serviceProvider:id,slug,business_name',
            'events.actor:id,first_name,last_name',
        ]);

        return $this->success('OK', [
            'contract' => (new ContractResource($contract))->resolve(),
            'events' => $contract->events->map(fn ($e) => [
                'type' => $e->type,
                'created_at' => optional($e->created_at)->toIso8601String(),
                'actor' => $e->relationLoaded('actor') ? [
                    'id' => $e->actor?->id,
                    'first_name' => $e->actor?->first_name,
                    'last_name' => $e->actor?->last_name,
                ] : null,
            ])->all(),
        ]);
    }

    public function send(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($lead->user_id === $request->user()->id, 403);

        if ($lead->contract_sent_at !== null) {
            return $this->success('Contract has already been sent.', []);
        }

        if (! in_array($lead->status, [LeadStatus::NEW, LeadStatus::CONTACTED], true)) {
            return $this->error('Contract can only be sent from a new or contacted inquiry.', [], 422);
        }

        $validated = $request->validate([
            'offered_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $this->lifecycle->offer($lead, $request->user(), isset($validated['offered_rate']) ? (float) $validated['offered_rate'] : null);

        return $this->success('Contract sent to provider. Waiting for provider acceptance.', []);
    }

    public function withdraw(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($lead->user_id === $request->user()->id, 403);

        if ($lead->contract_accepted_at !== null || in_array($lead->status, [LeadStatus::IN_PROGRESS, LeadStatus::CONVERTED], true)) {
            return $this->error('Accepted offers cannot be removed.', [], 422);
        }

        if ($lead->contract_sent_at === null) {
            return $this->success('No active offer to remove.', []);
        }

        $contract = $lead->contract ?: $this->lifecycle->ensureForLead($lead);
        $this->lifecycle->withdraw($contract, $request->user());

        return $this->success('Offer removed successfully.', []);
    }

    public function end(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($lead->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500', Rule::notIn(['null', 'undefined'])],
        ]);

        $contract = $lead->contract ?: $this->lifecycle->ensureForLead($lead);
        $this->lifecycle->end($contract, $request->user(), $validated['reason'] ?? null);

        return $this->success('Contract ended successfully.', []);
    }

    public function accept(Request $request, Contract $contract): JsonResponse
    {
        abort_unless($request->user()->serviceProvider?->id === $contract->service_provider_id, 403);

        $this->lifecycle->accept($contract, $request->user());

        return $this->success('Offer accepted.', []);
    }

    private function canAccess(Contract $contract, $user): bool
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
