<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\Project;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestSession>
 */
class TestSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'external_ref' => 'PROJ-'.fake()->numberBetween(1000, 1999),
            'title' => fake()->sentence(4),
            'description' => null,
            'base_commit' => fake()->sha1(),
            'head_commit' => fake()->sha1(),
            'only_after' => false,
            'repo_remote' => null,
            'branch' => 'feature/'.fake()->slug(2),
            'status' => SessionStatus::NotPassed,
            'agent' => 'Claude Code + Playwright',
        ];
    }

    public function onlyAfter(): static
    {
        return $this->state(fn () => [
            'only_after' => true,
            'base_commit' => null,
        ]);
    }

    public function status(SessionStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
