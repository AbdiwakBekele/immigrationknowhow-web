<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::create('service_providers', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Business Info
            $table->string('business_name')->nullable();
            $table->string('slug')->unique();
            $table->text('bio')->nullable();
            $table->text('description')->nullable();
            $table->string('tagline')->nullable();
            
            // Contact
            $table->string('business_email')->nullable();
            $table->string('business_phone')->nullable();
            $table->string('website')->nullable();
            
            // Service Details
            $table->json('service_types'); // Array of ServiceType enum values
            $table->json('specializations')->nullable();
            $table->json('languages_offered'); // Languages they can serve clients in
            
            // Pricing
            $table->string('pricing_model')->nullable(); // hourly, flat_rate, consultation, custom
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('consultation_fee', 10, 2)->nullable();
            $table->boolean('free_consultation')->default(false);
            $table->text('pricing_notes')->nullable();
            
            // Service Area
            $table->boolean('serves_remote')->default(false);
            $table->boolean('serves_in_person')->default(true);
            $table->integer('service_radius_miles')->nullable();
            $table->json('service_areas')->nullable(); // Specific cities/states
            
            // Credentials
            $table->string('license_number')->nullable();
            $table->string('license_state')->nullable();
            $table->date('license_expiry')->nullable();
            $table->json('certifications')->nullable();
            $table->integer('years_experience')->nullable();
            
            // Social Links
            $table->string('linkedin_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('tiktok_url')->nullable();
            
            // Stats
            $table->integer('total_leads')->default(0);
            $table->integer('total_reviews')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->integer('profile_views')->default(0);
            
            // Status
            $table->string('verification_status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('accepting_clients')->default(true);
            
            // Subscription
            $table->string('subscription_plan')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('verification_status');
            $table->index(['is_active', 'accepting_clients']);
            $table->index('is_featured');

            if ($driver !== 'sqlite') {
                $table->fullText(['business_name', 'bio', 'description']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_providers');
    }
};
