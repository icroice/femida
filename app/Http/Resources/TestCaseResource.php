<?php

namespace App\Http\Resources;

use App\Models\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TestCase */
class TestCaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'folder' => $this->folder,
            'preconditions' => $this->preconditions,
            'steps' => $this->steps,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
