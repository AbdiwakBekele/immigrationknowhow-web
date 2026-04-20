<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminLibraryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_store_ebook_with_wp_detail_fields(): void
    {
        Storage::fake('public');
        Storage::fake(LibraryItem::LIBRARY_MEDIA_DISK);

        $admin = $this->createAdmin();
        $category = LibraryCategory::create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.library.store'), [
            'title' => 'Sleepwalking Uncovered A Comprehensive Guide for Families',
            'type' => 'ebook',
            'all_regions' => true,
            'regions' => [],
            'category_id' => $category->id,
            'author_id' => null,
            'new_author_name' => 'Tayo Obatusin',
            'description' => 'Sleepwalking, or somnambulism, is a condition in which individuals walk or perform other complex activities while still asleep.',
            'publisher' => 'John Smith',
            'published_at' => '2025-11-11',
            'isbn' => '123456',
            'page_count' => 60,
            'language' => 'English',
            'estimated_reading_minutes' => 90,
            'difficulty_level' => 'Beginner',
            'recommended_age_group' => 'All',
            'pdf_file' => UploadedFile::fake()->create('sleepwalking.pdf', 24, 'application/pdf'),
            'audio_file' => UploadedFile::fake()->create('sleepwalking.mp3', 24, 'audio/mpeg'),
            'price' => '9.99',
            'currency' => 'usd',
            'is_active' => true,
            'is_featured' => false,
        ]);

        $item = LibraryItem::where('title', 'Sleepwalking Uncovered A Comprehensive Guide for Families')->firstOrFail();
        $response->assertRedirect(route('admin.library.show', $item->slug));

        $this->assertSame('John Smith', $item->publisher);
        $this->assertSame('2025-11-11', $item->published_at?->format('Y-m-d'));
        $this->assertSame(2025, $item->publication_year);
        $this->assertSame('123456', $item->isbn);
        $this->assertSame(60, $item->page_count);
        $this->assertSame('English', $item->language);
        $this->assertSame(90, $item->estimated_reading_minutes);
        $this->assertSame('Beginner', $item->difficulty_level);
        $this->assertSame('All', $item->recommended_age_group);
        $this->assertSame('Tayo Obatusin', $item->author);
        $this->assertTrue($item->is_premium);

        $this->assertTrue(Storage::disk(LibraryItem::LIBRARY_MEDIA_DISK)->exists($item->file_path));
        $this->assertTrue(Storage::disk(LibraryItem::LIBRARY_MEDIA_DISK)->exists($item->audio_file_path));
    }

    public function test_admin_can_update_and_delete_library_item(): void
    {
        Storage::fake('public');
        Storage::fake(LibraryItem::LIBRARY_MEDIA_DISK);

        $admin = $this->createAdmin();
        Storage::disk(LibraryItem::LIBRARY_MEDIA_DISK)->put('library/files/original.pdf', 'pdf');
        Storage::disk(LibraryItem::LIBRARY_MEDIA_DISK)->put('library/files/original.mp3', 'audio');

        $item = LibraryItem::create([
            'title' => 'Original Guide',
            'slug' => 'original-guide',
            'type' => 'ebook',
            'description' => 'Original description',
            'file_path' => 'library/files/original.pdf',
            'file_name' => 'original.pdf',
            'file_size' => 3,
            'file_type' => 'pdf',
            'audio_file_path' => 'library/files/original.mp3',
            'audio_file_name' => 'original.mp3',
            'audio_file_size' => 5,
            'audio_file_type' => 'mp3',
            'price' => 5,
            'currency' => 'USD',
            'is_premium' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.library.update', $item), [
            'title' => 'Updated Guide',
            'type' => 'ebook',
            'all_regions' => true,
            'regions' => [],
            'category_id' => null,
            'author_id' => null,
            'new_author_name' => 'Updated Author',
            'description' => 'Updated description',
            'publisher' => 'Updated Publisher',
            'published_at' => '2026-01-15',
            'isbn' => 'ABC-123',
            'page_count' => 120,
            'language' => 'English',
            'estimated_reading_minutes' => 75,
            'difficulty_level' => 'Intermediate',
            'recommended_age_group' => 'Adults',
            'price' => '0',
            'currency' => 'USD',
            'is_active' => true,
            'is_featured' => true,
        ]);

        $response->assertSessionHas('success');

        $item->refresh();
        $this->assertSame('Updated Guide', $item->title);
        $this->assertSame('updated-guide', $item->slug);
        $this->assertSame('Updated Author', $item->author);
        $this->assertSame('Updated Publisher', $item->publisher);
        $this->assertSame(120, $item->page_count);
        $this->assertSame('0.00', $item->price);
        $this->assertFalse($item->is_premium);
        $this->assertTrue($item->is_featured);

        $delete = $this->actingAs($admin)->delete(route('admin.library.destroy', $item));

        $delete->assertRedirect(route('admin.library.index'));
        $this->assertSoftDeleted('library_items', ['id' => $item->id]);
        $this->assertFalse(Storage::disk(LibraryItem::LIBRARY_MEDIA_DISK)->exists('library/files/original.pdf'));
        $this->assertFalse(Storage::disk(LibraryItem::LIBRARY_MEDIA_DISK)->exists('library/files/original.mp3'));
    }

    private function createAdmin(): User
    {
        $admin = User::create([
            'first_name' => 'Library',
            'last_name' => 'Admin',
            'email' => 'library-admin'.uniqid().'@example.com',
            'password' => Hash::make('Password123!'),
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ]);

        $admin->assignRole(UserRole::ADMIN->value);

        return $admin;
    }
}
