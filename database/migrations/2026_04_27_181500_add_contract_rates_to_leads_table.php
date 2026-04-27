<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->decimal('contract_offered_rate', 10, 2)->nullable()->after('contract_accepted_at');
            $table->decimal('contract_agreed_rate', 10, 2)->nullable()->after('contract_offered_rate');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['contract_offered_rate', 'contract_agreed_rate']);
        });
    }
};
