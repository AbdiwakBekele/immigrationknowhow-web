<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Relationships
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_provider_id')->constrained()->onDelete('cascade');
            
            // Lead Details
            $table->string('service_type');
            $table->string('status')->default('new');
            $table->text('message');
            $table->json('requirements')->nullable(); // Structured requirements
            
            // Contact Preference
            $table->string('preferred_contact_method')->default('message'); // message, email, phone
            $table->string('preferred_contact_time')->nullable();
            
            // Urgency
            $table->string('urgency')->default('normal'); // low, normal, high, urgent
            $table->date('needed_by')->nullable();
            
            // Budget
            $table->string('budget_range')->nullable();
            
            // Tracking
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            
            // Provider Response
            $table->text('provider_notes')->nullable();
            $table->string('decline_reason')->nullable();
            
            // Source Tracking
            $table->string('source')->default('marketplace'); // marketplace, referral, direct, affiliate
            $table->string('referral_code')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('status');
            $table->index(['service_provider_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
