<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\LibraryAuthor;
use App\Models\LibraryItem;
use App\Models\User;
use Database\Seeders\LibraryAuthorSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLibraryAuthorManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_library_authors(): void
    {
        $admin = $this->createAdmin();

        $index = $this->actingAs($admin)->get(route('admin.library-authors.index'));
        $index->assertRedirect(route('admin.library-categories.index'));

        $store = $this->actingAs($admin)->post(route('admin.library-authors.store'), [
            'name' => 'Jane Example, Esq.',
        ]);
        $store->assertRedirect(route('admin.library-categories.index'));
        $store->assertSessionHas('success');

        $author = LibraryAuthor::where('name', 'Jane Example, Esq.')->firstOrFail();

        $update = $this->actingAs($admin)->put(route('admin.library-authors.update', $author), [
            'name' => 'Jane Example, Attorney',
        ]);
        $update->assertRedirect(route('admin.library-categories.index'));
        $author->refresh();
        $this->assertSame('Jane Example, Attorney', $author->name);

        $destroy = $this->actingAs($admin)->delete(route('admin.library-authors.destroy', $author->slug));
        $destroy->assertRedirect(route('admin.library-categories.index'));
        $this->assertDatabaseMissing('library_authors', ['id' => $author->id]);
    }

    public function test_admin_cannot_delete_author_with_library_items(): void
    {
        $admin = $this->createAdmin();
        $author = LibraryAuthor::create(['name' => 'Linked Author']);

        LibraryItem::create([
            'title' => 'Sample Book',
            'slug' => 'sample-book',
            'type' => 'ebook',
            'file_path' => 'library/files/sample.pdf',
            'file_name' => 'sample.pdf',
            'file_size' => 1,
            'file_type' => 'pdf',
            'author_id' => $author->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.library-authors.destroy', $author));

        $response->assertRedirect(route('admin.library-categories.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('library_authors', ['id' => $author->id]);
    }

    public function test_library_author_seeder_normalizes_any_author_name_containing_tayo(): void
    {
        $legacy = LibraryAuthor::create(['name' => 'Tayo']);

        LibraryItem::create([
            'title' => 'Legacy Title',
            'slug' => 'legacy-title',
            'type' => 'ebook',
            'file_path' => 'library/files/legacy.pdf',
            'file_name' => 'legacy.pdf',
            'file_size' => 1,
            'file_type' => 'pdf',
            'author_id' => $legacy->id,
            'is_active' => true,
        ]);

        $this->seed(LibraryAuthorSeeder::class);

        $canonicalAuthor = LibraryAuthor::query()
            ->whereRaw('LOWER(name) LIKE ?', [LibraryAuthorSeeder::TAYO_NAME_MATCH])
            ->firstOrFail();

        $this->assertSame(LibraryAuthorSeeder::TAYO_OBATUSIN_CANONICAL_NAME, $canonicalAuthor->name);
        $this->assertSame(
            LibraryAuthorSeeder::TAYO_OBATUSIN_CANONICAL_NAME,
            LibraryItem::first()->fresh()->author
        );
        $this->assertSame(1, LibraryAuthor::query()->whereRaw('LOWER(name) LIKE ?', [LibraryAuthorSeeder::TAYO_NAME_MATCH])->count());
        $this->assertSame($canonicalAuthor->id, LibraryItem::first()->author_id);
    }

    private function createAdmin(): User
    {
        $admin = User::create([
            'first_name' => 'Library',
            'last_name' => 'Admin',
            'email' => 'library-author-admin'.uniqid().'@example.com',
            'password' => Hash::make('Password123!'),
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ]);

        $admin->assignRole(UserRole::ADMIN->value);

        return $admin;
    }
}
