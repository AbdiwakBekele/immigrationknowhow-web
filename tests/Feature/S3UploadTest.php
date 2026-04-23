<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class S3UploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_file_to_configured_s3_disk(): void
    {
        Storage::fake('s3');
        Config::set('uploads.s3.disk', 's3');
        Config::set('uploads.s3.directory', 'uploads');
        Config::set('uploads.s3.visibility', 'private');
        Config::set('uploads.s3.allowed_mimes', ['png', 'jpg', 'jpeg', 'pdf']);
        Config::set('uploads.s3.max_size_kb', 10240);

        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson(route('uploads.s3.store'), [
            'file' => UploadedFile::fake()->image('avatar.png')->size(512),
            'directory' => 'avatars',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'disk',
                'path',
                'visibility',
                'original_name',
                'mime_type',
                'size_bytes',
                'url',
                'temporary_url',
                'temporary_url_expires_at',
            ]);

        $path = $response->json('path');

        $this->assertNotNull($path);
        Storage::disk('s3')->assertExists($path);
        $this->assertStringStartsWith('uploads/avatars/', $path);
        $this->assertSame('private', $response->json('visibility'));
    }

    public function test_upload_rejects_file_types_not_in_allowed_mimes(): void
    {
        Storage::fake('s3');
        Config::set('uploads.s3.disk', 's3');
        Config::set('uploads.s3.allowed_mimes', ['png']);
        Config::set('uploads.s3.max_size_kb', 10240);

        $user = $this->createUser();

        $this->actingAs($user)->postJson(route('uploads.s3.store'), [
            'file' => UploadedFile::fake()->create('malware.exe', 64, 'application/x-msdownload'),
        ])->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    private function createUser(): User
    {
        return User::query()->create([
            'first_name' => 'Upload',
            'last_name' => 'Tester',
            'email' => 's3-upload-'.uniqid().'@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);
    }
}
