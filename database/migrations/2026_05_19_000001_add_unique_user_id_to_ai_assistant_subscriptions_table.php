<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_assistant_subscriptions')) {
            return;
        }

        $duplicateUserIds = DB::table('ai_assistant_subscriptions')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        foreach ($duplicateUserIds as $userId) {
            $rows = DB::table('ai_assistant_subscriptions')
                ->where('user_id', $userId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();

            $keepId = $rows->first()?->id;
            $removeIds = $rows->skip(1)->pluck('id')->all();
            if ($keepId && $removeIds !== []) {
                DB::table('ai_assistant_subscriptions')->whereIn('id', $removeIds)->delete();
            }
        }

        Schema::table('ai_assistant_subscriptions', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_assistant_subscriptions')) {
            return;
        }

        Schema::table('ai_assistant_subscriptions', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }
};
