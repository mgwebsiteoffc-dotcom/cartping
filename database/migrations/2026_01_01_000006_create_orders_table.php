<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('shopify_order_id')->index();
            $table->string('order_number');
            $table->string('name')->nullable();
            $table->string('status')->default('pending');
            $table->string('financial_status')->nullable();
            $table->string('fulfillment_status')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('subtotal_price', 14, 2)->default(0);
            $table->decimal('total_price', 14, 2)->default(0);
            $table->decimal('total_discounts', 14, 2)->default(0);
            $table->decimal('total_shipping', 14, 2)->default(0);
            $table->decimal('total_tax', 14, 2)->default(0);
            $table->json('line_items')->nullable();
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_company')->nullable();
            $table->string('tracking_url')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'shopify_order_id']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
