<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * The project permission matrix. The admin passes everything via Gate::before.
 */
class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $user->isMember($project);
    }

    /** Creating projects — admin only (Gate::before). */
    public function create(User $user): bool
    {
        return false;
    }

    /** Project settings — a project admin (the instance admin passes via Gate::before). */
    public function update(User $user, Project $project): bool
    {
        return $user->isProjectAdmin($project);
    }

    /** Deleting a project — admin only. */
    public function delete(User $user, Project $project): bool
    {
        return false;
    }
}
