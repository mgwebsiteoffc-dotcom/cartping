<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ctwa_ads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('meta_ad_id')->nullable();
            $table->string('meta_campaign_id')->nullable();
            $table->string('meta_adset_id')->nullable();
            $table->string('destination_wa_number')->nullable();
            $table->string('ctwa_template_id')->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('utm_source')->default('ctwa');
            $table->string('utm_medium')->default('meta');
            $table->string('utm_campaign')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('daily_budget', 14, 2)->nullable();
            $table->string('currency')->nullable();
            $table->decimal('cost', 14, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ctwa_ads');
    }
};
