<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('uszips')) {
            return;
        }

        Schema::create('uszips', function (Blueprint $table) {
            $table->id();
            $table->string('zip', 10)->index();
            $table->string('city')->index();
            $table->string('state', 2)->index();
            $table->string('state_id', 2)->nullable()->index();
            $table->string('state_name')->nullable();
            $table->string('county_name')->nullable()->index();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('county')->nullable();
            $table->string('timezone')->nullable();

            $table->index(['state_id', 'zip']);
            $table->index(['state_id', 'city']);
            $table->index(['state_id', 'county_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uszips');
    }
};
