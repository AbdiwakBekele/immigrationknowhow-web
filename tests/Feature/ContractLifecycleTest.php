<?php

namespace Tests\Feature;

use App\Enums\ContractState;
use App\Enums\LeadStatus;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Services\Contracts\ContractLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_accept_and_end_transitions_update_contract_and_lead(): void
    {
        $service = app(ContractLifecycleService::class);
        [$user, $providerUser, $provider, $lead] = $this->seedLead();

        $offered = $service->offer($lead, $user, 125.50);
        $this->assertSame(ContractState::OFFERED, $offered->state);
        $this->assertSame('125.50', (string) $offered->offered_rate);

        $accepted = $service->accept($offered, $providerUser);
        $this->assertSame(ContractState::ACCEPTED, $accepted->state);
        $this->assertSame('125.50', (string) $accepted->agreed_rate);

        $ended = $service->end($accepted, $user, 'Completed');
        $this->assertSame(ContractState::ENDED, $ended->state);

        $lead->refresh();
        $this->assertSame(LeadStatus::CLOSED, $lead->status);
        $this->assertNotNull($lead->contract_id);
    }

    public function test_withdrawn_offer_cannot_be_accepted(): void
    {
        $service = app(ContractLifecycleService::class);
        [$user, $providerUser, , $lead] = $this->seedLead();

        $offered = $service->offer($lead, $user, 99.00);
        $withdrawn = $service->withdraw($offered, $user);
        $this->assertSame(ContractState::WITHDRAWN, $withdrawn->state);

        $this->expectException(\RuntimeException::class);
        $service->accept($withdrawn, $providerUser);
    }

    private function seedLead(): array
    {
        $user = User::query()->create([
            'first_name' => 'Client',
            'last_name' => 'User',
            'email' => 'client@example.test',
            'password' => 'password',
            'onboarding_completed' => true,
        ]);

        $providerUser = User::query()->create([
            'first_name' => 'Provider',
            'last_name' => 'User',
            'email' => 'provider@example.test',
            'password' => 'password',
            'onboarding_completed' => true,
        ]);

        $provider = ServiceProvider::query()->create([
            'user_id' => $providerUser->id,
            'business_name' => 'Provider LLC',
            'slug' => 'provider-llc',
            'service_types' => ['translator'],
            'languages_offered' => ['en'],
            'pricing_model' => 'hourly',
            'hourly_rate' => 110.00,
        ]);

        $lead = Lead::query()->create([
            'user_id' => $user->id,
            'service_provider_id' => $provider->id,
            'service_type' => 'translator',
            'status' => LeadStatus::NEW,
            'message' => 'Need help with translation.',
            'preferred_contact_method' => 'message',
            'urgency' => 'normal',
            'source' => 'marketplace',
        ]);

        return [$user, $providerUser, $provider, $lead];
    }
}
