<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\TestCase;
use App\Models\User;
use App\Support\Access\AccessService;
use App\Support\Access\ModuleRegistry;
use App\Support\Access\Permission;

/**
 * Test case rights are the qa module 'cases' section abilities: they follow
 * module availability and membership through AccessService (spec §5).
 * Ability strings come from the registry — a typo fails loud, not as a grant.
 */
class TestCasePolicy
{
    public function __construct(private readonly AccessService $access) {}

    public function view(User $user, TestCase $testCase): bool
    {
        return $this->access->check($user, $testCase->project, ModuleRegistry::ability('qa', 'cases', Permission::View));
    }

    public function create(User $user, Project $project): bool
    {
        return $this->access->check($user, $project, ModuleRegistry::ability('qa', 'cases', Permission::Create));
    }

    public function update(User $user, TestCase $testCase): bool
    {
        return $this->access->check($user, $testCase->project, ModuleRegistry::ability('qa', 'cases', Permission::Update));
    }

    /** "Deleting" a case = moving it to inactive. */
    public function delete(User $user, TestCase $testCase): bool
    {
        return $this->access->check($user, $testCase->project, ModuleRegistry::ability('qa', 'cases', Permission::Delete));
    }
}
