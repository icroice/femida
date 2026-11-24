<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable board move (005): who, when, from which column to which.
 * from_column null = the creation event. No updates — only inserts.
 */
class TaskTransition extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['task_id', 'user_id', 'from_column', 'to_column'];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
