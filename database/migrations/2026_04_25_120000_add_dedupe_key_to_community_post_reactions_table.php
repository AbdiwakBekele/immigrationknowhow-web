<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('community_post_reactions', 'dedupe_key')) {
            Schema::table('community_post_reactions', function (Blueprint $table) {
                $table->string('dedupe_key', 64)->nullable()->after('type');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql' || Schema::getConnection()->getDriverName() === 'mariadb') {
            DB::statement("UPDATE community_post_reactions SET dedupe_key = CONCAT('u', user_id) WHERE (dedupe_key IS NULL OR dedupe_key = '') AND user_id IS NOT NULL");
        } else {
            $rows = DB::table('community_post_reactions')->select('id', 'user_id')->whereNotNull('user_id')->get();
            foreach ($rows as $row) {
                if ($row->user_id) {
                    DB::table('community_post_reactions')
                        ->where('id', $row->id)
                        ->whereNull('dedupe_key')
                        ->update(['dedupe_key' => 'u'.$row->user_id]);
                }
            }
        }

        $driver = Schema::getConnection()->getDriverName();

        // The composite unique begins with `community_post_id`, which the FK to `community_posts` may use; drop that FK first.
        try {
            Schema::table('community_post_reactions', function (Blueprint $table) {
                $table->dropForeign(['community_post_id']);
            });
        } catch (QueryException) {
        }
        // Legacy: some DBs have user_id -> users; drop if present.
        try {
            Schema::table('community_post_reactions', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (QueryException) {
        }

        if (Schema::hasIndex('community_post_reactions', 'community_post_user_type_unique', 'unique')) {
            Schema::table('community_post_reactions', function (Blueprint $table) {
                $table->dropUnique('community_post_user_type_unique');
            });
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            try {
                DB::statement('ALTER TABLE community_post_reactions MODIFY user_id BIGINT UNSIGNED NULL');
            } catch (QueryException) {
            }
            if (! $this->foreignKeyExists('community_post_reactions', 'user_id')) {
                try {
                    Schema::table('community_post_reactions', function (Blueprint $table) {
                        $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                    });
                } catch (QueryException) {
                }
            }
        } else {
            try {
                Schema::table('community_post_reactions', function (Blueprint $table) {
                    $table->unsignedBigInteger('user_id')->nullable()->change();
                });
            } catch (QueryException) {
            }
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            try {
                DB::statement('ALTER TABLE community_post_reactions MODIFY dedupe_key VARCHAR(64) NOT NULL');
            } catch (QueryException) {
            }
        } else {
            try {
                Schema::table('community_post_reactions', function (Blueprint $table) {
                    $table->string('dedupe_key', 64)->nullable(false)->change();
                });
            } catch (QueryException) {
            }
        }

        if (! Schema::hasIndex('community_post_reactions', 'community_post_type_dedupe_unique', 'unique')) {
            Schema::table('community_post_reactions', function (Blueprint $table) {
                $table->unique(['community_post_id', 'type', 'dedupe_key'], 'community_post_type_dedupe_unique');
            });
        }

        if (! $this->foreignKeyExists('community_post_reactions', 'community_post_id')) {
            try {
                Schema::table('community_post_reactions', function (Blueprint $table) {
                    $table->foreign('community_post_id')->references('id')->on('community_posts')->cascadeOnDelete();
                });
            } catch (QueryException) {
            }
        }
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        $keys = Schema::getForeignKeys($table);

        return collect($keys)->contains(function (array $fk) use ($column) {
            return in_array($column, $fk['columns'] ?? [], true);
        });
    }

    public function down(): void
    {
        try {
            Schema::table('community_post_reactions', function (Blueprint $table) {
                $table->dropForeign(['community_post_id']);
            });
        } catch (QueryException) {
        }
        if (Schema::hasIndex('community_post_reactions', 'community_post_type_dedupe_unique', 'unique')) {
            Schema::table('community_post_reactions', function (Blueprint $table) {
                $table->dropUnique('community_post_type_dedupe_unique');
            });
        }

        DB::table('community_post_reactions')->whereNull('user_id')->delete();

        if (Schema::hasColumn('community_post_reactions', 'dedupe_key')) {
            Schema::table('community_post_reactions', function (Blueprint $table) {
                $table->dropColumn('dedupe_key');
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            try {
                Schema::table('community_post_reactions', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            } catch (QueryException) {
            }
            try {
                DB::statement('ALTER TABLE community_post_reactions MODIFY user_id BIGINT UNSIGNED NOT NULL');
            } catch (QueryException) {
            }
            if (! $this->foreignKeyExists('community_post_reactions', 'user_id')) {
                Schema::table('community_post_reactions', function (Blueprint $table) {
                    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                });
            }
        } else {
            try {
                Schema::table('community_post_reactions', function (Blueprint $table) {
                    $table->unsignedBigInteger('user_id')->nullable(false)->change();
                });
            } catch (QueryException) {
            }
        }

        if (! Schema::hasIndex('community_post_reactions', 'community_post_user_type_unique', 'unique')) {
            Schema::table('community_post_reactions', function (Blueprint $table) {
                $table->unique(['community_post_id', 'user_id', 'type'], 'community_post_user_type_unique');
            });
        }

        if (! $this->foreignKeyExists('community_post_reactions', 'community_post_id')) {
            try {
                Schema::table('community_post_reactions', function (Blueprint $table) {
                    $table->foreign('community_post_id')->references('id')->on('community_posts')->cascadeOnDelete();
                });
            } catch (QueryException) {
            }
        }
    }
};
