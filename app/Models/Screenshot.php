<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A step screenshot. The file lives on a storage disk (disk screenshots),
 * the DB stores the relative path.
 */
class Screenshot extends Model
{
    public const DISK = 'screenshots';

    public $timestamps = false;

    protected $fillable = [
        'step_result_id', 'path', 'width', 'height', 'bytes', 'page_url', 'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StepResult, $this> */
    public function stepResult(): BelongsTo
    {
        return $this->belongsTo(StepResult::class);
    }

    public function absolutePath(): string
    {
        return Storage::disk(self::DISK)->path($this->path);
    }
}
