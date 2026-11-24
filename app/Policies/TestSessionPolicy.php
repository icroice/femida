<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\TestSession;
use App\Models\User;
use App\Support\Access\AccessService;

/**
 * Session rights are the qa module 'sessions' section abilities: they follow
 * module availability and membership through AccessService (spec §5).
 */
class TestSessionPolicy
{
    public function __construct(private readonly AccessService $access) {}

    public function view(User $user, TestSession $session): bool
    {
        return $this->access->check($user, $session->project, 'qa.sessions.view');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->access->check($user, $project, 'qa.sessions.create');
    }

    public function update(User $user, TestSession $session): bool
    {
        return $this->access->check($user, $session->project, 'qa.sessions.update');
    }

    /**
     * Any project member may set a verdict —
     * "the verdict belongs to a human, not the agent".
     */
    public function verdict(User $user, TestSession $session): bool
    {
        return $this->access->check($user, $session->project, 'qa.sessions.update');
    }

    /**
     * Manually adding/removing session cases — any project member.
     * No status gate: adding a case to a "passed" session automatically
     * returns it to not_passed.
     */
    public function attachCase(User $user, TestSession $session): bool
    {
        return $this->access->check($user, $session->project, 'qa.sessions.update');
    }

    /**
     * Sending a case for re-verification (resetting results) — any
     * member: it is the normal review cycle.
     */
    public function clearCase(User $user, TestSession $session): bool
    {
        return $this->access->check($user, $session->project, 'qa.sessions.update');
    }

    /**
     * A full session reset (including runs) costs more than a single
     * case — still the same sessions update right at the v2 start (v2-17).
     */
    public function clearSession(User $user, TestSession $session): bool
    {
        return $this->access->check($user, $session->project, 'qa.sessions.update');
    }
}
