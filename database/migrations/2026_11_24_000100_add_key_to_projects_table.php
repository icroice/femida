<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project key (JIRA-style, decision for 005): 2–10 uppercase letters,
 * globally unique, immutable after creation. The task number is
 * "{key}-{next_task_number}" within the project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('key', 10)->nullable()->after('slug');
            $table->unsignedInteger('next_task_number')->default(1)->after('key');
        });

        // Backfill from the slug: uppercase letters only; short/empty bases
        // fall back to PR+id, collisions get a numeric suffix.
        foreach (DB::table('projects')->orderBy('id')->get() as $project) {
            $base = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $project->slug) ?? '');
            $base = substr(strlen($base) < 2 ? 'PR'.$project->id : $base, 0, 10);

            $key = $base;
            $suffix = 1;
            while (DB::table('projects')->where('key', $key)->where('id', '!=', $project->id)->exists()) {
                $key = substr($base, 0, 10 - strlen((string) $suffix)).$suffix;
                $suffix++;
            }

            DB::table('projects')->where('id', $project->id)->update(['key' => $key]);
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->string('key', 10)->nullable(false)->change();
            $table->unique('key');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn(['key', 'next_task_number']);
        });
    }
};
