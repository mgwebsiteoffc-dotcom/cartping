<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('shopify_product_id');
            $table->string('title');
            $table->string('handle')->nullable();
            $table->text('body_html')->nullable();
            $table->string('product_type')->nullable();
            $table->string('vendor')->nullable();
            $table->json('tags')->nullable();
            $table->string('featured_image')->nullable();
            $table->decimal('price_min', 14, 2)->nullable();
            $table->decimal('price_max', 14, 2)->nullable();
            $table->string('currency')->nullable();
            $table->boolean('available')->default(false);
            $table->integer('inventory_total')->default(0);
            $table->string('status')->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'shopify_product_id']);
            $table->index(['store_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
