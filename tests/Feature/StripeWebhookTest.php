<?php

namespace Tests\Feature;

use App\Models\StripeWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_local_only_not_a_real_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.stripe.webhook_secret', $this->webhookSecret);
        Config::set('services.stripe.webhook_expect_live', false);
    }

    public function test_valid_signed_webhook_returns_200(): void
    {
        [$payload, $signature] = $this->signedEvent([
            'id' => 'evt_test_valid_1',
            'type' => 'ping',
            'livemode' => false,
            'data' => ['object' => ['object' => 'event']],
        ]);

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload)
            ->assertOk()
            ->assertSeeText('OK');

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_test_valid_1',
            'event_type' => 'ping',
            'processing_status' => StripeWebhookEvent::STATUS_PROCESSED,
        ]);
    }

    public function test_missing_signature_returns_400(): void
    {
        $payload = $this->eventJson([
            'id' => 'evt_test_missing_sig',
            'type' => 'ping',
            'livemode' => false,
            'data' => ['object' => ['object' => 'event']],
        ]);

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload)
            ->assertStatus(400);
    }

    public function test_invalid_signature_returns_400(): void
    {
        $payload = $this->eventJson([
            'id' => 'evt_test_bad_sig',
            'type' => 'ping',
            'livemode' => false,
            'data' => ['object' => ['object' => 'event']],
        ]);

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't=1,v1=notavalidsignature',
        ], $payload)
            ->assertStatus(400);
    }

    public function test_invalid_payload_returns_400(): void
    {
        $timestamp = time();
        $payload = '{not-json';
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $payload)
            ->assertStatus(400);
    }

    public function test_unknown_event_type_returns_200(): void
    {
        [$payload, $signature] = $this->signedEvent([
            'id' => 'evt_test_unknown_type',
            'type' => 'some.future.event',
            'livemode' => false,
            'data' => ['object' => ['object' => 'event']],
        ]);

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload)
            ->assertOk();
    }

    public function test_duplicate_event_does_not_reprocess(): void
    {
        [$payload, $signature] = $this->signedEvent([
            'id' => 'evt_test_duplicate',
            'type' => 'ping',
            'livemode' => false,
            'data' => ['object' => ['object' => 'event']],
        ]);

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ];

        $this->call('POST', '/webhooks/stripe', [], [], [], $headers, $payload)->assertOk();
        $this->call('POST', '/webhooks/stripe', [], [], [], $headers, $payload)->assertOk();

        $this->assertSame(1, StripeWebhookEvent::query()->where('stripe_event_id', 'evt_test_duplicate')->count());
        $this->assertSame(1, (int) StripeWebhookEvent::query()->where('stripe_event_id', 'evt_test_duplicate')->value('attempts'));
    }

    public function test_route_does_not_require_authentication(): void
    {
        [$payload, $signature] = $this->signedEvent([
            'id' => 'evt_test_no_auth',
            'type' => 'ping',
            'livemode' => false,
            'data' => ['object' => ['object' => 'event']],
        ]);

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload)
            ->assertOk();
    }

    public function test_csrf_does_not_block_webhook(): void
    {
        [$payload, $signature] = $this->signedEvent([
            'id' => 'evt_test_csrf',
            'type' => 'ping',
            'livemode' => false,
            'data' => ['object' => ['object' => 'event']],
        ]);

        // No X-XSRF-TOKEN / session CSRF token provided.
        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload)
            ->assertOk()
            ->assertDontSee('CSRF');
    }

    public function test_response_does_not_include_secrets(): void
    {
        [$payload, $signature] = $this->signedEvent([
            'id' => 'evt_test_no_secrets',
            'type' => 'ping',
            'livemode' => false,
            'data' => ['object' => ['object' => 'event']],
        ]);

        $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload);

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringNotContainsString($this->webhookSecret, $body);
        $this->assertStringNotContainsString('sk_', $body);
        $this->assertStringNotContainsString('whsec_', $body);
    }

    public function test_live_handler_rejects_test_mode_event_when_configured(): void
    {
        Config::set('services.stripe.webhook_expect_live', true);

        [$payload, $signature] = $this->signedEvent([
            'id' => 'evt_test_rejected_in_live',
            'type' => 'ping',
            'livemode' => false,
            'data' => ['object' => ['object' => 'event']],
        ]);

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload)
            ->assertStatus(400);
    }

    public function test_missing_webhook_secret_returns_503(): void
    {
        Config::set('services.stripe.webhook_secret', '');

        [$payload, $signature] = $this->signedEvent([
            'id' => 'evt_test_no_secret',
            'type' => 'ping',
            'livemode' => false,
            'data' => ['object' => ['object' => 'event']],
        ]);

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload)
            ->assertStatus(503);
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array{0: string, 1: string}
     */
    private function signedEvent(array $event): array
    {
        $payload = $this->eventJson($event);
        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        return [$payload, "t={$timestamp},v1={$signature}"];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function eventJson(array $event): string
    {
        $base = [
            'object' => 'event',
            'api_version' => '2024-06-20',
            'created' => time(),
            'pending_webhooks' => 1,
            'request' => ['id' => null, 'idempotency_key' => null],
        ];

        return json_encode(array_merge($base, $event), JSON_THROW_ON_ERROR);
    }
}
