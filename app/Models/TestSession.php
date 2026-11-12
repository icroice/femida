<?php

namespace App\Models;

use App\Enums\RunSide;
use App\Enums\RunStatus;
use App\Enums\SessionStatus;
use Database\Factories\TestSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A task session: a set of cases run on the base/head commits.
 * Table test_sessions ("sessions" is taken by a Laravel core table).
 */
class TestSession extends Model
{
    /** @use HasFactory<TestSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id', 'created_by', 'external_ref', 'title', 'description',
        'base_commit', 'head_commit', 'only_after', 'repo_remote', 'branch',
        'status', 'agent', 'tokens_in', 'tokens_out',
    ];

    protected function casts(): array
    {
        return [
            'only_after' => 'boolean',
            'status' => SessionStatus::class,
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<TestCase, $this, SessionCase, 'pivot'> */
    public function cases(): BelongsToMany
    {
        return $this->belongsToMany(TestCase::class, 'session_case', 'session_id', 'case_id')
            ->using(SessionCase::class)
            ->withPivot(['position', 'agent_suggestion', 'agent_notes', 'verdict', 'verdict_comment', 'verdict_by', 'verdict_at'])
            ->orderByPivot('position');
    }

    /** @return HasMany<Run, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(Run::class, 'session_id');
    }

    /**
     * Only two statuses are stored: passed / not_passed.
     * Intermediate states are not stored — derived from runs:
     * awaiting_run — no runs yet, or not all sides started;
     * running — an unfinished run exists;
     * awaiting_review — all expected runs are finished.
     */
    public function flowState(): string
    {
        $runs = $this->runs()->get();

        if ($runs->contains(fn ($run) => in_array($run->status, [RunStatus::Pending, RunStatus::Running], true))) {
            return 'running';
        }

        $expected = $this->only_after ? 1 : 2;

        return $runs->whereNotNull('status')->count() >= $expected ? 'awaiting_review' : 'awaiting_run';
    }

    public function run(RunSide $side): ?Run
    {
        return $this->runs->firstWhere('side', $side);
    }
}
