<?php

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\User;

class ApiKeyPolicy
{
    // viewAny/create are not implemented: key lists and issuing live in the
    // Livewire components (ProjectKeys/ProfileKeys), which check rights
    // themselves (authorize('update', $project) / isMember) — wrapper
    // policies were dead code.

    /** Revoking a key — any member of the key's project (v2-17). */
    public function delete(User $user, ApiKey $apiKey): bool
    {
        return $user->isMember($apiKey->project);
    }
}
