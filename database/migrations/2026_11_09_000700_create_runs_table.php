<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('test_sessions')->cascadeOnDelete();
            $table->string('side', 6); // before | after
            $table->string('commit', 40);
            $table->string('status', 16)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unique(['session_id', 'side']); // one run per side
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runs');
    }
};
