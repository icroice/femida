<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('folder')->nullable();
            $table->string('title', 500);
            $table->text('preconditions')->nullable();
            $table->json('steps'); // [{action, expected}]
            $table->string('priority', 2)->default('P2');
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->index('folder');
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_cases');
    }
};
