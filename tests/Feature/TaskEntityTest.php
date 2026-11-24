<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class TaskEntityTest extends TestCase
{
    use RefreshDatabase;

    private TaskService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(TaskService::class);
    }

    public function test_numbers_are_sequential_and_prefixed_with_the_project_key(): void
    {
        $project = Project::factory()->create(['key' => 'HB']);
        $author = User::factory()->create();

        $one = $this->service->create($project, 'First', 'qa.intake', $author);
        $two = $this->service->create($project, 'Second', 'qa.verify', $author);

        $this->assertSame(1, $one->number);
        $this->assertSame(2, $two->number);
        $this->assertSame('HB-1', $one->code());
        $this->assertSame('HB-2', $two->code());
    }

    public function test_counters_are_per_project(): void
    {
        $alpha = Project::factory()->create(['key' => 'AA']);
        $beta = Project::factory()->create(['key' => 'BB']);
        $author = User::factory()->create();

        $this->service->create($alpha, 'A1', 'qa.intake', $author);
        $this->service->create($alpha, 'A2', 'qa.intake', $author);
        $inBeta = $this->service->create($beta, 'B1', 'qa.intake', $author);

        $this->assertSame(1, $inBeta->number);
        $this->assertSame('BB-1', $inBeta->code());
    }

    public function test_a_rolled_back_create_returns_the_number_to_the_pool(): void
    {
        $project = Project::factory()->create(['key' => 'FEM']);
        $author = User::factory()->create();
        $this->service->create($project, 'stays', 'qa.intake', $author);

        try {
            DB::transaction(function () use ($project, $author): void {
                $this->service->create($project, 'rolls back', 'qa.intake', $author);
                throw new RuntimeException('rollback');
            });
            $this->fail('the outer transaction must roll back');
        } catch (RuntimeException) {
            // expected
        }

        $task = $this->service->create($project, 'reuses the number', 'qa.intake', $author);
        $this->assertSame(2, $task->number, 'the counter rolls back with the transaction — no gaps');
        $this->assertSame(
            ['stays', 'reuses the number'],
            $project->tasks()->orderBy('number')->pluck('title')->all(),
            'the rolled-back task is gone'
        );
    }

    public function test_duplicate_project_number_is_rejected_by_the_index(): void
    {
        $project = Project::factory()->create();
        $existing = Task::factory()->create(['project_id' => $project]);

        $this->expectException(QueryException::class);
        Task::factory()->create(['project_id' => $project, 'number' => $existing->number]);
    }

    public function test_creation_and_moves_are_recorded_as_history(): void
    {
        $project = Project::factory()->create(['key' => 'FEM']);
        $author = User::factory()->create();
        $mover = User::factory()->create();

        $task = $this->service->create($project, 'History', 'qa.intake', $author);
        $this->service->moveTo($task, 'qa.verify', $mover);
        $this->service->moveTo($task->refresh(), 'qa.verify', $mover); // no-op, no noise
        $moved = $this->service->moveTo($task->refresh(), 'qa.done', $author);

        $this->assertSame('qa.done', $moved->refresh()->column);

        $history = $task->transitions()->orderBy('id')->get();
        $this->assertCount(3, $history);

        [$created, $toVerify, $toDone] = $history->all();
        $this->assertSame([$author->id, null, 'qa.intake'], [$created->user_id, $created->from_column, $created->to_column]);
        $this->assertSame([$mover->id, 'qa.intake', 'qa.verify'], [$toVerify->user_id, $toVerify->from_column, $toVerify->to_column]);
        $this->assertSame([$author->id, 'qa.verify', 'qa.done'], [$toDone->user_id, $toDone->from_column, $toDone->to_column]);

        $this->assertNotNull($created->created_at, 'the transition stamps when');
    }

    public function test_unknown_columns_are_rejected(): void
    {
        $project = Project::factory()->create();
        $author = User::factory()->create();

        try {
            $this->service->create($project, 'Nope', 'qa.nope', $author);
            $this->fail('an unknown column id must be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('column', $e->errors());
        }

        $task = $this->service->create($project, 'Ok', 'qa.intake', $author);

        try {
            $this->service->moveTo($task, 'dev.todo', $author); // module not in the registry
            $this->fail('a column of an unknown module must be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('column', $e->errors());
        }
    }

    public function test_empty_title_is_rejected(): void
    {
        $project = Project::factory()->create();
        $author = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->service->create($project, '  ', 'qa.intake', $author);
    }

    public function test_assignee_survives_user_deletion_and_history_keeps_its_author(): void
    {
        $project = Project::factory()->create();
        $assignee = User::factory()->create();
        $author = User::factory()->create();
        $task = $this->service->create($project, 'Assigned', 'qa.intake', $author, null, $assignee->id);

        $assignee->delete();
        $this->assertNull($task->refresh()->assignee_id, 'nullOnDelete: the task outlives the assignee');

        $this->expectException(QueryException::class);
        $author->delete(); // restrictOnDelete: history keeps its author
    }

    public function test_project_keys_are_unique(): void
    {
        Project::factory()->create(['key' => 'DUP']);

        $this->expectException(QueryException::class);
        Project::factory()->create(['key' => 'DUP']);
    }
}
