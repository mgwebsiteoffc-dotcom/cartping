<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('template_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('body');
            $table->json('header')->nullable();
            $table->json('buttons')->nullable();
            $table->string('status')->default('draft');
            $table->string('lifecycle')->default('draft');
            $table->string('provider_template_id')->nullable();
            $table->json('compliance_issues')->nullable();
            $table->boolean('is_control')->default(false);
            $table->json('metrics')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_variants');
    }
};
