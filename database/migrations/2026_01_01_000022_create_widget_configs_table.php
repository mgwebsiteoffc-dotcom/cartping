<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('type')->default('chat_widget');
            $table->json('launcher')->nullable();
            $table->json('simple_button')->nullable();
            $table->json('tooltip')->nullable();
            $table->json('chat_widget')->nullable();
            $table->json('smart_contextual')->nullable();
            $table->json('entry_popup')->nullable();
            $table->json('exit_popup')->nullable();
            $table->json('page_targeting')->nullable();
            $table->json('display_rules')->nullable();
            $table->string('install_mode')->default('manual');
            $table->string('script_tag_id')->nullable();
            $table->unsignedInteger('session_ttl_minutes')->default(720);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_configs');
    }
};
