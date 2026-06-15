<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebook_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->string('issued_for', 32)->default('signup');
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('library_item_id')->nullable()->constrained('library_items')->nullOnDelete();
            $table->foreignId('library_user_access_id')->nullable()->constrained('library_user_access')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'redeemed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebook_coupons');
    }
};
