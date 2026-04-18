<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed a default super admin account.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'admin@example.com')],
            [
                'first_name' => env('SUPER_ADMIN_FIRST_NAME', 'Super'),
                'last_name' => env('SUPER_ADMIN_LAST_NAME', 'Admin'),
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'ChangeMe123!')),
                'email_verified_at' => now(),
                'is_active' => true,
                'onboarding_completed' => true,
                'onboarding_completed_at' => now(),
            ]
        );

        // Keep this account aligned with the highest-privilege role (idempotent: no duplicate users).
        $user->syncRoles([UserRole::SUPER_ADMIN->value]);
    }
}
