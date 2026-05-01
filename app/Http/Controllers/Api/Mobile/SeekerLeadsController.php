<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\ConversationResource;
use App\Models\Lead;
use App\Models\ServiceProvider;
use App\Services\Contracts\ContractLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeekerLeadsController extends Controller
{
    public function __construct(
        protected ContractLifecycleService $contractLifecycle
    ) {}

    public function store(Request $request, ServiceProvider $provider): JsonResponse
    {
        $this->authorize('create', Lead::class);

        $sender = $request->user();

        $allowedServiceTypes = ! empty($provider->service_types)
            ? $provider->service_types
            : [ServiceType::OTHER->value];

        $validated = $request->validate([
            'service_type' => ['required', 'string', Rule::in($allowedServiceTypes)],
            'message' => ['required', 'string', 'min:2', 'max:2000'],
            'requirements' => ['nullable', 'array'],
            'preferred_contact_method' => ['nullable', 'in:message,email,phone'],
            'preferred_contact_time' => ['nullable', 'string', 'max:255'],
            'urgency' => ['required', 'in:low,normal,high,urgent'],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
            'budget_range' => ['nullable', 'string', 'max:100'],
            'intent' => ['nullable', Rule::in(['inquiry', 'offer'])],
            'offered_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $validated['preferred_contact_method'] = $validated['preferred_contact_method'] ?? 'message';
        $intent = $validated['intent'] ?? 'inquiry';
        unset($validated['intent']);

        $defaultProviderRate = $provider->hourly_rate !== null ? round((float) $provider->hourly_rate, 2) : null;
        $offeredRate = array_key_exists('offered_rate', $validated) && $validated['offered_rate'] !== null
            ? round((float) $validated['offered_rate'], 2)
            : $defaultProviderRate;
        unset($validated['offered_rate']);

        $lead = \DB::transaction(function () use ($validated, $provider, $sender, $intent, $offeredRate) {
            $lead = Lead::create([
                ...$validated,
                'user_id' => $sender->id,
                'service_provider_id' => $provider->id,
                'source' => $sender->affiliate_referral_id ? 'affiliate' : 'marketplace',
                'referral_code' => $sender->referredByAffiliate?->code,
                'affiliate_referral_id' => $sender->affiliate_referral_id,
            ]);

            $provider->incrementLeadCount();

            $conversation = $lead->createConversation();
            $conversation->addMessage($sender, $lead->message);

            if ($intent === 'offer') {
                $this->contractLifecycle->offer($lead, $sender, $offeredRate);
            }

            return $lead->refresh();
        });

        $conversation = $lead->conversation()->with([
            'user:id,first_name,last_name,avatar',
            'serviceProvider.user:id,first_name,last_name,avatar',
            'latestMessage',
            'lead:id,uuid,service_type,status',
        ])->first();

        return response()->json([
            'success' => true,
            'message' => $intent === 'offer'
                ? 'Your offer has been sent. Waiting for provider acceptance.'
                : 'Your inquiry has been sent! The provider will respond soon.',
            'data' => [
                'lead_uuid' => $lead->uuid,
                'conversation' => $conversation ? (new ConversationResource($conversation))->resolve() : null,
            ],
        ]);
    }
}
