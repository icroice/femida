<?php

namespace App\Support\Access;

use App\Models\Project;
use App\Models\User;

/**
 * The single place answering "what can this user do in this project"
 * (spec §5). Controllers, policies, Livewire and Blade ask it via ability
 * strings and never decide on their own.
 *
 * Abilities:
 *  - core:   'project.view' (member), 'project.settings'/'project.team'
 *            (project admin), 'board.<verb>' (member);
 *  - module: '<module>.<section>.<verb>' — held by any project member
 *            (decision v2-18: modules come available to everyone, no
 *            enablement gating). Unknown modules, undeclared sections
 *            and unknown verbs grant nothing (fail-closed).
 *
 * The instance admin and the project admin pass any check in the project.
 * At the v2 start (decision v2-17) every member holds all module verbs;
 * module business roles are a reserved extension (ModuleRole) that plugs
 * in here without changing the API.
 */
final class AccessService
{
    public function check(User $user, Project $project, string $ability): bool
    {
        if ($user->isProjectAdmin($project)) {
            return true;
        }

        if (! $user->isMember($project)) {
            return false;
        }

        // Core abilities of the project shell.
        return match ($ability) {
            'project.view' => true,
            'project.settings', 'project.team' => false,
            default => $this->checkNonCore($ability),
        };
    }

    /**
     * Board and module abilities of a plain member.
     */
    private function checkNonCore(string $ability): bool
    {
        // The board is the member workspace (spec §6): any verb, any member.
        if (str_starts_with($ability, 'board.')) {
            return true;
        }

        // Module ability '<module>.<section>.<verb>': the full triple must
        // name a registry module, a section it declares and a known verb —
        // a typo or a stale section denies, never grants (fail-closed).
        if (substr_count($ability, '.') !== 2) {
            return false;
        }

        [$moduleId, $sectionId, $verb] = explode('.', $ability);

        $module = ModuleRegistry::find($moduleId);

        return $module !== null
            && $module->hasSection($sectionId)
            && Permission::tryFrom($verb) !== null;
    }
}
