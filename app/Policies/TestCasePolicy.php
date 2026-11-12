<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\TestCase;
use App\Models\User;

class TestCasePolicy
{
    public function view(User $user, TestCase $testCase): bool
    {
        return $user->isMember($testCase->project);
    }

    /** Creating/editing cases (incl. steps) — any project member. */
    public function create(User $user, Project $project): bool
    {
        return $user->isMember($project);
    }

    public function update(User $user, TestCase $testCase): bool
    {
        return $user->isMember($testCase->project);
    }

    /** "Deleting" a case = moving it to inactive — developer+ only. */
    public function delete(User $user, TestCase $testCase): bool
    {
        return $user->isDeveloper($testCase->project);
    }
}
