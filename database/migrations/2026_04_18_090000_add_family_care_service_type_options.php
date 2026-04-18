<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_type_options')) {
            return;
        }

        $now = now();
        $nextSortOrder = (int) DB::table('service_type_options')->max('sort_order') + 1;

        foreach ($this->options($now, $nextSortOrder) as $option) {
            DB::table('service_type_options')->updateOrInsert(
                ['value' => $option['value']],
                $option
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_type_options')) {
            return;
        }

        DB::table('service_type_options')
            ->whereIn('value', ['babysitter', 'pet_sitter'])
            ->delete();
    }

    private function options($now, int $nextSortOrder): array
    {
        return [
            [
                'value' => 'babysitter',
                'label' => 'Babysitter / Child Care',
                'icon' => 'heart',
                'for_user' => true,
                'for_provider' => true,
                'is_active' => true,
                'sort_order' => $nextSortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'value' => 'pet_sitter',
                'label' => 'Pet Sitter / Pet Care',
                'icon' => 'heart',
                'for_user' => true,
                'for_provider' => true,
                'is_active' => true,
                'sort_order' => $nextSortOrder + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
    }
};
