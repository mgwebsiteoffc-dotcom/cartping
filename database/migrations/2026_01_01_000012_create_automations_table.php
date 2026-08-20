<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger'); // order_created | order_shipped | order_cancelled | abandoned_checkout | abandoned_browse | welcome
            $table->unsignedInteger('delay_after_minutes')->default(0);
            $table->foreignUuid('template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agent_flow')->nullable();
            $table->json('conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('send_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'trigger', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};
