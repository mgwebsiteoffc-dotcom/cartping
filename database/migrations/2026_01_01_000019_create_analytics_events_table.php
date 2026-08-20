<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->string('event')->index();
            $table->string('category')->index();
            $table->foreignUuid('contact_id')->nullable()->index();
            $table->foreignUuid('conversation_id')->nullable()->index();
            $table->foreignUuid('template_id')->nullable()->index();
            $table->foreignUuid('ad_id')->nullable()->index();
            $table->foreignUuid('automation_id')->nullable()->index();
            $table->string('session_id')->nullable();
            $table->decimal('value', 16, 2)->nullable();
            $table->string('channel')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
