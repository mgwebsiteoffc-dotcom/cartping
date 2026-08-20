<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('model')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.4);
            $table->boolean('enabled')->default(true);
            $table->boolean('autonomous')->default(true);
            $table->text('persona')->nullable();
            $table->text('greeting')->nullable();
            $table->string('timezone')->nullable();
            $table->json('enabled_tools')->nullable();
            $table->json('escalation_triggers')->nullable();
            $table->text('handoff_message')->nullable();
            $table->decimal('confidence_threshold', 4, 3)->default(0.55);
            $table->boolean('rag_enabled')->default(true);
            $table->string('knowledge_base_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_configs');
    }
};
