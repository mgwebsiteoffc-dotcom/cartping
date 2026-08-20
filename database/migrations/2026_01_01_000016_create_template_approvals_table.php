<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('template_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level'); // internal | compliance | provider
            $table->string('status')->default('pending');
            $table->string('reviewer_type')->nullable();
            $table->string('reviewer_id')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('provider_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_approvals');
    }
};
