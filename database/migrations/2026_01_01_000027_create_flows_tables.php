<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('trigger')->default('welcome');   // welcome | new_message | keyword | ...
            $table->string('trigger_value')->nullable();      // keywords / event
            $table->json('nodes')->nullable();                // graph definition
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('runs_count')->default(0);
            $table->unsignedBigInteger('completions_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'is_active']);
        });

        Schema::create('flow_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('flow_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('current_node_id')->nullable();
            $table->string('state')->default('running');
            $table->json('steps')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'flow_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_runs');
        Schema::dropIfExists('flows');
    }
};
