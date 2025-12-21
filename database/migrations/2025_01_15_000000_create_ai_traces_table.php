<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_traces', function (Blueprint $table) {
            $table->uuid('trace_id')->primary();
            $table->uuid('parent_trace_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('provider')->index();
            $table->string('model');
            $table->string('prompt_identifier')->nullable()->index();
            $table->string('prompt_version')->nullable();
            $table->json('sanitized_input')->nullable();
            $table->json('tools_registered')->nullable();
            $table->json('tools_called')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->json('validation_errors')->nullable();
            $table->json('schema_errors')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('estimated_cost', 10, 4)->default(0);
            $table->longText('final_output')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'created_at']);
            $table->index(['prompt_identifier', 'prompt_version']);
            $table->foreign('parent_trace_id')->references('trace_id')->on('ai_traces')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_traces');
    }
};

