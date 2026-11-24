<?php

namespace App\Observers;

use App\Models\ModuleState;
use App\Models\Project;

/**
 * Seeds module availability for a new project. The qa module is the v1
 * functionality (decision v2-7) — every project starts with it provided
 * and enabled. The enablement lifecycle UI (004) rewrites these rows
 * later; the engine only reads them.
 */
class ProjectObserver
{
    public function created(Project $project): void
    {
        ModuleState::query()->create([
            'project_id' => $project->id,
            'module' => 'qa',
            'provided_by_admin' => true,
            'enabled_by_org' => true,
        ]);
    }
}
