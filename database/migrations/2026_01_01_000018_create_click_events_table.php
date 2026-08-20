<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('click_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ctwa_ad_id')->constrained()->cascadeOnDelete();
            $table->string('click_id');
            $table->string('fingerprint')->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('referrer')->nullable();
            $table->timestamp('clicked_at');
            $table->boolean('converted')->default(false);
            $table->bigInteger('order_id')->nullable();
            $table->decimal('order_total', 14, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'fingerprint', 'converted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('click_events');
    }
};
