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
            'view advertiser dashboard',
            'view ads',
            'create ads',
            'edit ads',
            'delete ads',
            'view ad analytics',
            'manage ads',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $advertiserRole = Role::findOrCreate(UserRole::ADVERTISER->value, 'web');
        $advertiserRole->syncPermissions([
            'view advertiser dashboard',
            'view ads',
            'create ads',
            'edit ads',
            'delete ads',
            'view ad analytics',
        ]);

        Role::findOrCreate(UserRole::ADMIN->value, 'web')->givePermissionTo(['manage ads', 'view ad analytics']);
        Role::findOrCreate(UserRole::SUPER_ADMIN->value, 'web')->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $advertiserRole = Role::where('name', UserRole::ADVERTISER->value)->where('guard_name', 'web')->first();
        $advertiserRole?->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

