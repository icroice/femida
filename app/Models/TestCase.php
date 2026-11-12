<?php

namespace App\Models;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use Database\Factories\TestCaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A test case is a reusable unit of the project library.
 * steps: [{action: string, expected: string}]
 */
class TestCase extends Model
{
    /** @use HasFactory<TestCaseFactory> */
    use HasFactory;

    /** Folder: up to two levels separated by /, letters/digits/dash/dot/space (no quotes or brackets — C1). */
    public const FOLDER_RULES = ['nullable', 'string', 'max:255', 'regex:#^[\w\-. ]+(/[\w\-. ]+)?$#u'];

    protected $fillable = [
        'project_id', 'folder', 'title', 'preconditions', 'steps', 'priority', 'status',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'priority' => CasePriority::class,
            'status' => CaseStatus::class,
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<SessionCase, $this> */
    public function sessionCases(): HasMany
    {
        return $this->hasMany(SessionCase::class, 'case_id');
    }

    /**
     * @param  Builder<TestCase>  $query
     * @return Builder<TestCase>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CaseStatus::Active);
    }

    /** Normalize the folder path: trim segments, drop empties; '' → null. */
    public static function normalizeFolder(?string $folder): ?string
    {
        $segments = collect(explode('/', (string) $folder))
            ->map(fn (string $segment) => trim($segment))
            ->filter()
            ->values();

        return $segments->isEmpty() ? null : $segments->implode('/');
    }
}
