<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Support\Access\AccessService;

/**
 * The project core abilities — membership and administration — resolved
 * by AccessService (spec §5). The instance admin passes via Gate::before.
 */
class ProjectPolicy
{
    public function __construct(private readonly AccessService $access) {}

    public function view(User $user, Project $project): bool
    {
        return $this->access->check($user, $project, 'project.view');
    }

    /** Creating projects — admin only (Gate::before). */
    public function create(User $user): bool
    {
        return false;
    }

    /** Project settings — a project admin ('project.settings'). */
    public function update(User $user, Project $project): bool
    {
        return $this->access->check($user, $project, 'project.settings');
    }

    /** Deleting a project — admin only. */
    public function delete(User $user, Project $project): bool
    {
        return false;
    }
}
