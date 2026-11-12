<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "sessions" is taken by a Laravel core sessions table (database driver)
        Schema::create('test_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_ref'); // task number in the external tracker
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->string('base_commit', 40)->nullable(); // null in "after only" mode
            $table->string('head_commit', 40);
            $table->boolean('only_after')->default(false);
            $table->string('repo_remote')->nullable();
            $table->string('branch')->nullable();
            $table->string('status', 16)->default('not_passed');
            $table->text('agent')->nullable();
            $table->unsignedBigInteger('tokens_in')->nullable();
            $table->unsignedBigInteger('tokens_out')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'external_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_sessions');
    }
};
