<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('assignee_id')->nullable();
            $table->string('channel')->default('wa');
            $table->string('status')->default('open');
            $table->string('agent_mode')->default('auto');
            $table->decimal('ai_confidence', 4, 3)->nullable();
            $table->string('escalated_reason')->nullable();
            $table->string('source')->default('inbound');
            $table->bigInteger('shopify_order_id')->nullable();
            $table->string('ctwa_click_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
