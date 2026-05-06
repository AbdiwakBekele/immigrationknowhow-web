<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context')->default('user')->index(); // user | provider | mobile
            $table->string('role')->index(); // user | assistant
            $table->text('content');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'context', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_messages');
    }
};

