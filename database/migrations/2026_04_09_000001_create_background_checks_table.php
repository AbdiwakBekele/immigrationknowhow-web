<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('background_checks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('service_provider_id')->constrained()->cascadeOnDelete();
            
            // Checkr IDs
            $table->string('checkr_candidate_id')->nullable()->index();
            $table->string('checkr_invitation_id')->nullable();
            $table->string('checkr_report_id')->nullable()->index();
            
            // Status tracking
            $table->string('status')->default('pending'); // pending, invited, completed, clear, consider, suspended, dispute
            $table->string('adjudication')->nullable(); // engaged, adverse_action, etc.
            $table->string('package')->default('basic_criminal'); // The Checkr package used
            
            // Candidate info (stored for reference)
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('zipcode')->nullable();
            $table->date('dob')->nullable();
            $table->string('ssn_last_four')->nullable(); // Only store last 4 for reference
            
            // Results
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('report_summary')->nullable(); // Summary of findings
            $table->json('metadata')->nullable(); // Full Checkr response data
            
            // Webhook tracking
            $table->timestamp('last_webhook_at')->nullable();
            $table->json('webhook_history')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });

        // Add background check status to service_providers
        Schema::table('service_providers', function (Blueprint $table) {
            $table->string('background_check_status')->nullable()->after('verification_status');
            $table->timestamp('background_check_verified_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn(['background_check_status', 'background_check_verified_at']);
        });
        
        Schema::dropIfExists('background_checks');
    }
};
