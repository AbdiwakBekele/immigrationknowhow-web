<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ads')
            ->where('status', 'pending_payment')
            ->where('price_cents', '>', 0)
            ->update(['price_cents' => 2499]);
    }

    public function down(): void
    {
        DB::table('ads')
            ->where('status', 'pending_payment')
            ->where('price_cents', 2499)
            ->update(['price_cents' => 999]);
    }
};
