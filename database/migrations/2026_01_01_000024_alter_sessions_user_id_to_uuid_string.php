<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The framework migration originally created sessions.user_id as a bigint
     * (foreignId), but Store/User use UUID primary keys. Convert the column to
     * a string so UUIDs aren't truncated ("Data truncated for column 'user_id'").
     * This lets already-migrated databases be fixed with a plain `php artisan
     * migrate` (no wipe needed).
     */
    public function up(): void
    {
        if (Schema::hasColumn('sessions', 'user_id')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->string('user_id', 36)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sessions', 'user_id')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->change();
            });
        }
    }
};
