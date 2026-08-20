<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whatify uses an API key (+ optional API secret) rather than a bearer token.
     * Add the columns so a store can connect via Whatify and switch providers easily.
     */
    public function up(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            $table->text('api_key')->nullable()->after('token');
            $table->text('api_secret')->nullable()->after('api_key');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            $table->dropColumn(['api_key', 'api_secret']);
        });
    }
};
