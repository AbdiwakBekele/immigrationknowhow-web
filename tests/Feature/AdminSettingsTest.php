<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_can_update_company_name_and_separate_logos(): void
    {
        Storage::fake('public');

        $superAdmin = $this->createUser(['email' => 'super-admin@example.com']);
        $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

        $response = $this->actingAs($superAdmin)->patch(route('admin.settings.update'), [
            'company_name' => 'Acme Immigration Group',
            'maintenance_mode' => true,
            'reviews_auto_approve' => true,
            'email_notifications' => true,
            'new_provider_alerts' => false,
            'site_logo' => UploadedFile::fake()->image('site-logo.png', 300, 120),
            'admin_logo' => UploadedFile::fake()->image('admin-logo.png', 300, 120),
        ]);

        $response->assertSessionHas('success');

        $settings = PlatformSetting::query()->firstOrFail();

        $this->assertSame('Acme Immigration Group', $settings->company_name);
        $this->assertTrue($settings->maintenance_mode);
        $this->assertTrue($settings->reviews_auto_approve);
        $this->assertTrue($settings->email_notifications);
        $this->assertFalse($settings->new_provider_alerts);
        $this->assertNotNull($settings->site_logo_path);
        $this->assertNotNull($settings->admin_logo_path);
        $this->assertNotSame($settings->site_logo_path, $settings->admin_logo_path);

        $this->assertTrue(Storage::disk('public')->exists($settings->site_logo_path));
        $this->assertTrue(Storage::disk('public')->exists($settings->admin_logo_path));

        $this->get('/')
            ->assertOk()
            ->assertSee('Acme Immigration Group', false);
    }

    public function test_super_admin_can_upload_svg_logos_and_update_general_configuration(): void
    {
        Storage::fake('public');

        $superAdmin = $this->createUser(['email' => 'svg-admin@example.com']);
        $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

        $siteSvg = UploadedFile::fake()->createWithContent('site-logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><text y="15">Site</text></svg>');
        $adminSvg = UploadedFile::fake()->createWithContent('admin-logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><text y="15">Admin</text></svg>');

        $response = $this->actingAs($superAdmin)->patch(route('admin.settings.update'), [
            'company_name' => 'Vector Immigration',
            'support_email' => 'support@vector.test',
            'support_phone' => '+1-555-0101',
            'support_address' => '100 Market Street, Miami, FL',
            'site_tagline' => 'A sharper path to immigration services.',
            'footer_tagline' => 'Trusted immigration support, beautifully branded.',
            'maintenance_mode' => false,
            'reviews_auto_approve' => false,
            'email_notifications' => true,
            'new_provider_alerts' => true,
            'site_logo' => $siteSvg,
            'admin_logo' => $adminSvg,
        ]);

        $response->assertSessionHas('success');

        $settings = PlatformSetting::query()->firstOrFail();

        $this->assertSame('support@vector.test', $settings->support_email);
        $this->assertSame('+1-555-0101', $settings->support_phone);
        $this->assertSame('100 Market Street, Miami, FL', $settings->support_address);
        $this->assertSame('A sharper path to immigration services.', $settings->site_tagline);
        $this->assertSame('Trusted immigration support, beautifully branded.', $settings->footer_tagline);
        $this->assertTrue(Str::endsWith($settings->site_logo_path, '.svg'));
        $this->assertTrue(Str::endsWith($settings->admin_logo_path, '.svg'));

        $this->assertTrue(Storage::disk('public')->exists($settings->site_logo_path));
        $this->assertTrue(Storage::disk('public')->exists($settings->admin_logo_path));

        $this->get('/')
            ->assertOk()
            ->assertSee('A sharper path to immigration services.', false)
            ->assertSee('support@vector.test', false)
            ->assertSee('Trusted immigration support, beautifully branded.', false);
    }

    public function test_regular_admin_cannot_access_super_admin_settings(): void
    {
        $admin = $this->createUser(['email' => 'admin@example.com']);
        $admin->assignRole(UserRole::ADMIN->value);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    protected function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'user'.uniqid().'@example.com',
            'password' => Hash::make('Password123!'),
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ], $overrides));
    }
}
