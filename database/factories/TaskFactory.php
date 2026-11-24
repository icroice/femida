<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'number' => null, // set by the service in tests; direct creation fills it below
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(8),
            'assignee_id' => User::factory(),
            'column' => 'qa.intake',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Task $task): void {
            // direct Task::create() without a number takes the next counter
            // value — factories stay consistent with the unique index
            if (($task->getAttributes()['number'] ?? null) === null) {
                $task->number = (int) $task->project->next_task_number;
                $task->project->increment('next_task_number');
            }
        });
    }
}
