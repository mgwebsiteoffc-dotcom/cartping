<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('myshopify_domain')->unique();
            $table->string('contact_email')->nullable();
            $table->string('password')->nullable();
            $table->string('currency')->default('USD');
            $table->string('timezone')->default('UTC');
            $table->unsignedTinyInteger('onboarding_step')->default(1);
            $table->boolean('onboarding_complete')->default(false);
            $table->json('features')->nullable();
            $table->string('shopify_scope')->nullable();
            $table->text('access_token')->nullable();
            $table->json('settings')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
