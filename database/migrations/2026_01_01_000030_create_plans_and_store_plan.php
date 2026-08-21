<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();          // free | starter | pro | enterprise
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->json('limits')->nullable();         // e.g. { conversations: 500, broadcasts: 1000 }
            $table->json('features')->nullable();       // e.g. { flows: true, campaigns: true }
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->foreignUuid('plan_id')->nullable()->after('settings')->index();
            $table->timestamp('plan_expires_at')->nullable()->after('plan_id');
            $table->timestamp('disabled_at')->nullable()->after('plan_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['plan_id', 'plan_expires_at', 'disabled_at']);
        });
        Schema::dropIfExists('plans');
    }
};
