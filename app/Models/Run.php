<?php

namespace App\Models;

use App\Enums\RunSide;
use App\Enums\RunStatus;
use Database\Factories\RunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A session run on one side: head ("after") or base ("before").
 * One run per side — unique (session_id, side).
 */
class Run extends Model
{
    /** @use HasFactory<RunFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'session_id', 'side', 'commit', 'status', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'side' => RunSide::class,
            'status' => RunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TestSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TestSession::class, 'session_id');
    }

    /** @return HasMany<StepResult, $this> */
    public function stepResults(): HasMany
    {
        return $this->hasMany(StepResult::class);
    }
}
