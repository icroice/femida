<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-step module availability (spec §5): provided_by_admin — the instance
 * admin granted the module to the project; enabled_by_org — the project
 * enabled it. The engine (AccessService) reads; the lifecycle UI (004)
 * writes. One row per project and module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('module');
            $table->boolean('provided_by_admin')->default(false);
            $table->boolean('enabled_by_org')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_states');
    }
};
