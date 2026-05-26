<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
            ServiceTypeOptionSeeder::class,
            SubscriptionPlanSeeder::class,
            EmailTemplateSeeder::class,
            UsZipSeeder::class,
            LibraryAuthorSeeder::class,
        ]);
    }
}
