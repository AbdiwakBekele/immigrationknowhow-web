<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 20); // outgoing|incoming
            $table->string('status', 30)->default('received'); // sent|received|failed|queued
            $table->string('subject')->nullable();
            $table->string('from_email')->nullable();
            $table->string('to_email')->nullable();
            $table->text('cc')->nullable();
            $table->text('bcc')->nullable();
            $table->string('message_id')->nullable();
            $table->string('provider')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['direction', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
