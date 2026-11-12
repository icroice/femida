<?php

namespace App\Http\Resources;

use App\Models\TestSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A compact session representation for lists (/sessions uses standard
 * Laravel pagination — the same links/meta format as /cases).
 * Full session data (cases, steps, verdicts) — SessionPayload in GET /sessions/{id}.
 *
 * @mixin TestSession
 */
class TestSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_ref' => $this->external_ref,
            'title' => $this->title,
            'status' => $this->status->value,
            'only_after' => $this->only_after,
            'base_commit' => $this->base_commit,
            'head_commit' => $this->head_commit,
            'agent' => $this->agent,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
