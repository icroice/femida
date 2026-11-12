<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screenshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_result_id')->constrained()->cascadeOnDelete();
            $table->string('path'); // relative path on the screenshots disk
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->string('page_url')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->index('step_result_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screenshots');
    }
};
