<?php

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\User;

class ApiKeyPolicy
{
    // viewAny/create are not implemented: key lists and issuing live in the
    // Livewire components (ProjectKeys/ProfileKeys), which check rights
    // themselves (authorize('update', $project) / isDeveloper) — wrapper
    // policies were dead code.

    /** Revoking a key — developer+ of the key's project. */
    public function delete(User $user, ApiKey $apiKey): bool
    {
        return $user->isDeveloper($apiKey->project);
    }
}
