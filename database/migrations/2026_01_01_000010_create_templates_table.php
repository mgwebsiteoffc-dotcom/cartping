<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('category')->default('MARKETING');
            $table->string('language')->default('en');
            $table->text('body');
            $table->json('header')->nullable();
            $table->string('footer')->nullable();
            $table->json('buttons')->nullable();
            $table->json('variables')->nullable();
            $table->string('status')->default('draft');
            $table->string('lifecycle')->default('draft');
            $table->string('provider_template_id')->nullable();
            $table->string('approval_level')->default('internal');
            $table->json('compliance_issues')->nullable();
            $table->boolean('is_ab_test')->default(false);
            $table->string('ab_group')->nullable();
            $table->json('metrics')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
