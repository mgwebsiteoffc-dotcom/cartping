<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->string('shop')->nullable();
            $table->string('topic')->index();
            $table->bigInteger('shopify_order_id')->nullable()->index();
            $table->string('api_version')->nullable();
            $table->string('shopify_domain')->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->string('processed_by_job')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
