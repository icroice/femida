<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Access\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The task lifecycle contract (005) — the only way tasks appear and move.
 *
 * - the number comes from the project counter under a row lock: concurrent
 *   creations serialize, a rollback returns the number to the pool (no gaps);
 * - every create/move writes an immutable transition (who/when/from/to);
 * - a column must exist in ModuleRegistry ('<module>.<column>');
 * - the assignee must be a member of the task's project (TZ §4).
 */
class TaskService
{
    /**
     * Create a task in a column; records the creation transition.
     */
    public function create(
        Project $project,
        string $title,
        string $column,
        User $author,
        ?string $description = null,
        ?int $assigneeId = null,
    ): Task {
        self::contractError(ModuleRegistry::hasColumn($column), "Unknown board column '{$column}'", 'column');
        self::contractError(trim($title) !== '', 'The title is required', 'title');
        self::contractError(mb_strlen(trim($title)) <= 500, 'The title must be at most 500 characters', 'title');
        self::contractError(
            $assigneeId === null || $project->users()->whereKey($assigneeId)->exists(),
            'The assignee must be a member of the project',
            'assignee_id'
        );

        return DB::transaction(function () use ($project, $title, $column, $author, $description, $assigneeId): Task {
            // lock the counter row: concurrent creates serialize here
            $locked = Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();
            $number = (int) $locked->next_task_number;
            $locked->forceFill(['next_task_number' => $number + 1])->save();

            $task = $project->tasks()->create([
                'number' => $number,
                'title' => trim($title),
                'description' => $description,
                'assignee_id' => $assigneeId,
                'column' => $column,
            ]);
            $task->transitions()->create([
                'user_id' => $author->id,
                'from_column' => null, // creation event
                'to_column' => $column,
            ]);

            return $task;
        });
    }

    /**
     * Move a task to another column; records the transition.
     * Moving to the current column is a no-op (no history noise).
     */
    public function moveTo(Task $task, string $column, User $actor): Task
    {
        self::contractError(ModuleRegistry::hasColumn($column), "Unknown board column '{$column}'", 'column');

        return DB::transaction(function () use ($task, $column, $actor): Task {
            // lock the task row: two parallel moves must not record
            // transitions with the same stale origin
            $locked = Task::query()->whereKey($task->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->column;

            if ($from !== $column) {
                $locked->forceFill(['column' => $column])->save();
                $locked->transitions()->create([
                    'user_id' => $actor->id,
                    'from_column' => $from,
                    'to_column' => $column,
                ]);
            }

            // keep the caller's handle in sync with the locked row
            $task->setRawAttributes($locked->getAttributes(), true);

            return $task;
        });
    }

    private static function contractError(bool $ok, string $message, string $key = 'input'): void
    {
        if (! $ok) {
            throw ValidationException::withMessages([$key => $message]);
        }
    }
}
