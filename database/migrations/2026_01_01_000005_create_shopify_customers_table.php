<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('shopify_id');
            $table->string('email')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('accepts_marketing')->default(false);
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('total_spent', 14, 2)->default(0);
            $table->decimal('lifetime_value', 14, 2)->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->json('address')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'shopify_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_customers');
    }
};
