<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view affiliates',
            'manage affiliates',
            'view affiliate dashboard',
            'view affiliate earnings',
            'view affiliate payouts',
            'edit affiliate profile',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $affiliateRole = Role::findOrCreate(UserRole::AFFILIATE->value, 'web');
        $affiliateRole->syncPermissions([
            'view affiliates',
            'view affiliate dashboard',
            'view affiliate earnings',
            'view affiliate payouts',
            'edit affiliate profile',
        ]);

        $adminRole = Role::findOrCreate(UserRole::ADMIN->value, 'web');
        $adminRole->givePermissionTo(['view affiliates', 'manage affiliates']);

        $superAdminRole = Role::findOrCreate(UserRole::SUPER_ADMIN->value, 'web');
        $superAdminRole->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $affiliateRole = Role::where('name', UserRole::AFFILIATE->value)->where('guard_name', 'web')->first();
        $affiliateRole?->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
