<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User permissions
            'view users',
            'create users',
            'edit users',
            'delete users',
            
            // Provider permissions
            'view providers',
            'create providers',
            'edit providers',
            'delete providers',
            'verify providers',
            
            // Lead permissions
            'view leads',
            'create leads',
            'edit leads',
            'delete leads',
            'respond to leads',
            
            // Message permissions
            'view messages',
            'send messages',
            'delete messages',
            
            // Review permissions
            'view reviews',
            'create reviews',
            'edit reviews',
            'delete reviews',
            'moderate reviews',
            'respond to reviews',
            
            // Library permissions
            'view library',
            'manage library',
            'download library items',
            
            // Affiliate permissions
            'view affiliates',
            'manage affiliates',
            
            // Video permissions
            'view videos',
            'manage videos',
            
            // Verification permissions
            'view verifications',
            'manage verifications',
            
            // Admin permissions
            'access admin panel',
            'view reports',
            'manage settings',
            'manage roles',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Create roles and assign permissions
        
        // General User (Immigrant)
        $userRole = Role::firstOrCreate([
            'name' => UserRole::USER->value,
            'guard_name' => 'web',
        ]);
        $userRole->syncPermissions([
            'view providers',
            'create leads',
            'view leads',
            'view messages',
            'send messages',
            'view reviews',
            'create reviews',
            'view library',
            'download library items',
            'view videos',
            'view affiliates',
        ]);

        // Service Provider
        $providerRole = Role::firstOrCreate([
            'name' => UserRole::PROVIDER->value,
            'guard_name' => 'web',
        ]);
        $providerRole->syncPermissions([
            'view providers',
            'edit providers', // Can edit own profile
            'view leads',
            'respond to leads',
            'view messages',
            'send messages',
            'view reviews',
            'respond to reviews',
            'view library',
            'download library items',
            'view videos',
            'view affiliates',
        ]);

        // Admin
        $adminRole = Role::firstOrCreate([
            'name' => UserRole::ADMIN->value,
            'guard_name' => 'web',
        ]);
        $adminRole->syncPermissions([
            'view users',
            'edit users',
            'view providers',
            'edit providers',
            'verify providers',
            'view leads',
            'edit leads',
            'view messages',
            'view reviews',
            'moderate reviews',
            'view library',
            'manage library',
            'view affiliates',
            'manage affiliates',
            'view videos',
            'manage videos',
            'view verifications',
            'manage verifications',
            'access admin panel',
            'view reports',
        ]);

        // Super Admin - has all permissions
        $superAdminRole = Role::firstOrCreate([
            'name' => UserRole::SUPER_ADMIN->value,
            'guard_name' => 'web',
        ]);
        $superAdminRole->syncPermissions(Permission::pluck('name')->all());
    }
}
