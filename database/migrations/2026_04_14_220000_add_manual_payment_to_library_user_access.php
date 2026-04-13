<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_user_access', function (Blueprint $table) {
            $table->timestamp('manual_payment_requested_at')->nullable();
            $table->string('manual_payment_reference', 255)->nullable();
            $table->text('manual_payment_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('library_user_access', function (Blueprint $table) {
            $table->dropColumn([
                'manual_payment_requested_at',
                'manual_payment_reference',
                'manual_payment_note',
            ]);
        });
    }
};
