<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\TestSession;
use App\Models\User;

class TestSessionPolicy
{
    public function view(User $user, TestSession $session): bool
    {
        return $user->isMember($session->project);
    }

    /** Sessions are created and driven by the agent/developer (via a developer-level key). */
    public function create(User $user, Project $project): bool
    {
        return $user->isDeveloper($project);
    }

    public function update(User $user, TestSession $session): bool
    {
        return $user->isDeveloper($session->project);
    }

    /**
     * Any project member may set a verdict —
     * "the verdict belongs to a human, not the agent".
     */
    public function verdict(User $user, TestSession $session): bool
    {
        return $user->isMember($session->project);
    }

    /**
     * Manually adding/removing session cases — any project member.
     * No status gate: adding a case to a "passed" session automatically
     * returns it to not_passed.
     */
    public function attachCase(User $user, TestSession $session): bool
    {
        return $user->isMember($session->project);
    }

    /**
     * Sending a case for re-verification (resetting results) — any
     * member, including testers: it is the normal review cycle.
     */
    public function clearCase(User $user, TestSession $session): bool
    {
        return $user->isMember($session->project);
    }

    /**
     * A full session reset (including runs) — developer+: the session
     * is tied to a commit, a full reset costs more than a single case.
     */
    public function clearSession(User $user, TestSession $session): bool
    {
        return $user->isDeveloper($session->project);
    }
}
