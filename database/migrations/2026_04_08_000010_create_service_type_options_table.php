<?php

use App\Enums\ServiceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_type_options', function (Blueprint $table) {
            $table->id();
            $table->string('value')->unique();
            $table->string('label');
            $table->string('icon')->nullable();
            $table->boolean('for_user')->default(true);
            $table->boolean('for_provider')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $rows = collect(ServiceType::cases())->map(fn (ServiceType $type, int $i) => [
            'value' => $type->value,
            'label' => $type->label(),
            'icon' => $type->icon(),
            'for_user' => true,
            'for_provider' => true,
            'is_active' => true,
            'sort_order' => $i + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        DB::table('service_type_options')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('service_type_options');
    }
};
