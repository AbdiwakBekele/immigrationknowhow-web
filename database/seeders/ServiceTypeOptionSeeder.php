<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServiceTypeOptionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('service_type_options')) {
            return;
        }

        $now = now();
        $nextSortOrder = (int) DB::table('service_type_options')->max('sort_order') + 1;

        $options = [
            ['value' => 'electricians', 'label' => 'Electricians', 'icon' => 'bolt'],
            ['value' => 'plumbers', 'label' => 'Plumbers', 'icon' => 'wrench-screwdriver'],
            ['value' => 'hvac_repair_services', 'label' => 'HVAC repair services', 'icon' => 'cog-6-tooth'],
            ['value' => 'mechanic', 'label' => 'Mechanic', 'icon' => 'wrench-screwdriver'],
            ['value' => 'nail_and_beauty_salon', 'label' => 'Nail and Beauty salon', 'icon' => 'sparkles'],
            ['value' => 'errand_services', 'label' => 'Help services', 'icon' => 'briefcase'],
            ['value' => 'lawn_care', 'label' => 'Lawn care', 'icon' => 'sun'],
            ['value' => 'trees_cutters', 'label' => 'Trees cutters', 'icon' => 'scissors'],
            ['value' => 'snow_removal_service', 'label' => 'Snow removal service', 'icon' => 'cloud'],
            ['value' => 'realtors', 'label' => 'Realtors', 'icon' => 'home'],
        ];

        foreach ($options as $index => $option) {
            DB::table('service_type_options')->updateOrInsert(
                ['value' => $option['value']],
                [
                    'label' => $option['label'],
                    'icon' => $option['icon'],
                    'for_user' => true,
                    'for_provider' => true,
                    'is_active' => true,
                    'sort_order' => $nextSortOrder + $index,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
