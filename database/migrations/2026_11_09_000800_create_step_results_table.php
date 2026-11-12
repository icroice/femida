<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_case_id')->constrained('session_case')->cascadeOnDelete();
            $table->unsignedInteger('step_index');
            $table->string('status', 10); // passed | failed | skipped | error
            $table->text('note')->nullable();
            $table->unsignedInteger('elapsed_ms')->nullable();
            $table->unique(['run_id', 'session_case_id', 'step_index']);
            $table->index('session_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_results');
    }
};
