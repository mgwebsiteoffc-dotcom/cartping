<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shopify now issues expiring offline access tokens (required for new
     * public apps as of April 1, 2026). Add columns to store the refresh token
     * and its expiry alongside the (short-lived) access token.
     */
    public function up(): void
    {
        Schema::table('shopify_connections', function (Blueprint $table) {
            $table->text('refresh_token')->nullable()->after('access_token');
            $table->timestamp('refresh_token_expires_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_connections', function (Blueprint $table) {
            $table->dropColumn(['refresh_token', 'refresh_token_expires_at']);
        });
    }
};
