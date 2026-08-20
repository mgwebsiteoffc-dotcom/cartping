<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->string('mode')->default('oauth'); // oauth | manual
            $table->string('shop');
            $table->text('access_token');
            $table->text('scope')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['store_id']);
            $table->index('shop');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_connections');
    }
};
