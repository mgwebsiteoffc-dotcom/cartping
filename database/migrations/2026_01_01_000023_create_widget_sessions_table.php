<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->string('session_key');
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fingerprint')->nullable()->index();
            $table->string('page_type')->default('home');
            $table->timestamp('landed_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->json('viewed_product_ids')->nullable();
            $table->json('cart')->nullable();
            $table->string('cart_token')->nullable();
            $table->string('checkout_url')->nullable();
            $table->boolean('opted_in_wa')->default(false);
            $table->string('wa_number')->nullable();
            $table->string('source')->default('widget');
            $table->string('ctwa_click_id')->nullable();
            $table->string('referrer')->nullable();
            $table->boolean('bounced')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'session_key']);
            $table->index(['store_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_sessions');
    }
};
