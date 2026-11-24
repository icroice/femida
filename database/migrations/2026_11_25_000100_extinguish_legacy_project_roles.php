<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The v1 roles owner/tester were dropped by v2-17; this release carries
 * the extinguishing migration (spec §5): owner → admin, tester →
 * developer (spec §4 role mapping). A no-op on a fresh v2 database:
 * there is no v1→v2 upgrade path (v2-16) — this only guards an instance
 * that points v2 code at a v1 database, where the ProjectRole enum cast
 * would otherwise crash on the old role strings.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_user')->where('role', 'owner')->update(['role' => 'admin']);
        DB::table('project_user')->where('role', 'tester')->update(['role' => 'developer']);
    }

    public function down(): void
    {
        // Irreversible: v2 has no owner/tester roles to restore to.
    }
};
