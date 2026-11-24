<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\TestCase;
use App\Models\User;
use App\Support\Access\AccessService;

/**
 * Test case rights are the qa module 'cases' section abilities: they follow
 * module availability and membership through AccessService (spec §5).
 */
class TestCasePolicy
{
    public function __construct(private readonly AccessService $access) {}

    public function view(User $user, TestCase $testCase): bool
    {
        return $this->access->check($user, $testCase->project, 'qa.cases.view');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->access->check($user, $project, 'qa.cases.create');
    }

    public function update(User $user, TestCase $testCase): bool
    {
        return $this->access->check($user, $testCase->project, 'qa.cases.update');
    }

    /** "Deleting" a case = moving it to inactive. */
    public function delete(User $user, TestCase $testCase): bool
    {
        return $this->access->check($user, $testCase->project, 'qa.cases.delete');
    }
}
