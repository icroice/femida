<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A board task (005): belongs to a project, lives in a module column
 * ('<module>.<column>' from ModuleRegistry). The visible number is
 * "{project key}-{number}", e.g. FEM-128. Moves are recorded as
 * immutable transitions — the history is the source of truth for
 * who/when/from/to.
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id', 'number', 'title', 'description', 'assignee_id', 'column',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TaskTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(TaskTransition::class);
    }

    /** The display number: {key}-{number}, e.g. FEM-128. */
    public function code(): string
    {
        return $this->project->key.'-'.$this->number;
    }
}
