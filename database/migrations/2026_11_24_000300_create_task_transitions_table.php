<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // history keeps its author
            $table->string('from_column')->nullable(); // null = the creation event
            $table->string('to_column');
            $table->timestamp('created_at')->nullable(); // when; immutable — no updated_at
            $table->index('task_id'); // history lookups: WHERE task_id on the largest table
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_transitions');
    }
};
