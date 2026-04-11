<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->string('site_logo_path')->nullable();
            $table->string('admin_logo_path')->nullable();
            $table->boolean('maintenance_mode')->default(false);
            $table->boolean('reviews_auto_approve')->default(false);
            $table->boolean('email_notifications')->default(true);
            $table->boolean('new_provider_alerts')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};