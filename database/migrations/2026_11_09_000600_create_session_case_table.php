<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_case', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('test_sessions')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('test_cases')->restrictOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('agent_suggestion', 16)->nullable();
            $table->text('agent_notes')->nullable();
            $table->string('verdict', 16)->nullable();
            $table->text('verdict_comment')->nullable();
            $table->foreignId('verdict_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verdict_at')->nullable();
            $table->unique(['session_id', 'case_id']);
            $table->index('case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_case');
    }
};
