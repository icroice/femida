<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module availability for a project (spec §5): two-step — provided by the
 * instance admin, enabled by the project. Module ids are registry slugs
 * (code), so no foreign key; a stale row is dead data, never a 500.
 */
class ModuleState extends Model
{
    protected $fillable = ['project_id', 'module', 'provided_by_admin', 'enabled_by_org'];

    protected function casts(): array
    {
        return [
            'provided_by_admin' => 'boolean',
            'enabled_by_org' => 'boolean',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
