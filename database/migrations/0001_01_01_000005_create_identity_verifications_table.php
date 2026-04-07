<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Document Type
            $table->string('document_type'); // passport, drivers_license, national_id, professional_license
            $table->string('document_number')->nullable();
            $table->string('document_country')->nullable();
            $table->date('document_expiry')->nullable();
            
            // Files
            $table->string('front_image')->nullable();
            $table->string('back_image')->nullable();
            $table->string('selfie_image')->nullable();
            
            // Verification Status
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->json('verification_notes')->nullable();
            
            // Admin Review
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verifications');
    }
};
