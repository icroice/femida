<?php

namespace App\Models;

use App\Enums\StepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The result of one case step in a specific run.
 * The UI's before/after pair is keyed by (session_case_id, step_index).
 */
class StepResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'run_id', 'session_case_id', 'step_index', 'status', 'note', 'elapsed_ms',
    ];

    protected function casts(): array
    {
        return [
            'status' => StepStatus::class,
        ];
    }

    /** @return BelongsTo<Run, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    /** @return BelongsTo<SessionCase, $this> */
    public function sessionCase(): BelongsTo
    {
        return $this->belongsTo(SessionCase::class, 'session_case_id');
    }

    /** @return HasMany<Screenshot, $this> */
    public function screenshots(): HasMany
    {
        return $this->hasMany(Screenshot::class);
    }
}
