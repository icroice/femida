<?php

namespace App\Models;

use App\Enums\Verdict;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A case attached to a session: position, agent suggestion and human verdict.
 * verdict describes head behavior.
 *
 * @property Verdict|null $agent_suggestion
 * @property Verdict|null $verdict
 */
class SessionCase extends Pivot
{
    public $incrementing = true;

    public $timestamps = false;

    protected $table = 'session_case';

    protected $fillable = [
        'session_id', 'case_id', 'position',
        'agent_suggestion', 'agent_notes',
        'verdict', 'verdict_comment', 'verdict_by', 'verdict_at',
    ];

    protected function casts(): array
    {
        return [
            'agent_suggestion' => Verdict::class,
            'verdict' => Verdict::class,
            'verdict_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TestSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TestSession::class, 'session_id');
    }

    /** @return BelongsTo<TestCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(TestCase::class, 'case_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verdictBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verdict_by');
    }

    /** @return HasMany<StepResult, $this> */
    public function stepResults(): HasMany
    {
        return $this->hasMany(StepResult::class, 'session_case_id');
    }
}
