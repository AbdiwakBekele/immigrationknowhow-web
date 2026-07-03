<?php

namespace Tests\Unit;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\TransactionalEmailTemplateRenderer;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TransactionalEmailTemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_ebook_coupon_tokens_are_replaced_in_body_and_action_url(): void
    {
        $user = User::create([
            'first_name' => 'Maya',
            'last_name' => 'Reader',
            'email' => 'maya@example.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        $renderer = app(TransactionalEmailTemplateRenderer::class);
        $payload = $renderer->render(EmailTemplate::EVENT_EBOOK_COUPON, $user, [
            'role' => 'user',
            'coupon_code' => 'IKH-ABCD-EFGH',
            'library_link' => 'https://example.com/library',
        ]);

        $this->assertStringNotContainsString('{{coupon', $payload['body']);
        $this->assertStringContainsString('IKH-ABCD-EFGH', $payload['body']);
        $this->assertSame('https://example.com/library', $payload['action_url']);
    }

    public function test_coupon_alias_token_is_supported(): void
    {
        $renderer = app(TransactionalEmailTemplateRenderer::class);
        $payload = $renderer->render(EmailTemplate::EVENT_EBOOK_COUPON, null, [
            'role' => 'user',
            'coupon' => 'IKH-ALIAS-CODE',
            'library_link' => url('/library'),
        ]);

        $this->assertSame('IKH-ALIAS-CODE', $payload['tokens']['{{coupon}}']);
        $this->assertSame('IKH-ALIAS-CODE', $payload['tokens']['{{coupon_code}}']);
    }
}
