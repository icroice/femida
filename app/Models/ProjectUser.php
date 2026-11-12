<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Project membership: role = developer | tester.
 */
class ProjectUser extends Pivot
{
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = ['project_id', 'user_id', 'role'];

    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
