<?php

namespace App\Support\Access;

use App\Models\ModuleState;
use App\Models\Project;
use App\Models\User;

/**
 * The single place answering "what can this user do in this project"
 * (spec §5). Controllers, policies, Livewire and Blade ask it via ability
 * strings and never decide on their own.
 *
 * Abilities:
 *  - core:   'project.view' (member), 'project.settings'/'project.team'/
 *            'project.modules' (project admin), 'board.<verb>' (member);
 *  - module: '<module>.<section>.<verb>' — the module must be provided
 *            AND enabled (module_states).
 *
 * Access = membership AND provided AND enabled AND right. At the v2 start
 * (decision v2-17) project roles are just admin/developer: module verbs are
 * granted to every member; module business roles are a reserved extension
 * (ModuleRole) that plugs in here without changing the API. The instance
 * admin passes any check.
 */
final class AccessService
{
    /** @var array<int, array<string, bool>> availability memo per project */
    private array $moduleMemo = [];

    public function check(User $user, Project $project, string $ability): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isMember($project)) {
            return false;
        }

        // Core abilities of the project shell.
        return match ($ability) {
            'project.view' => true,
            'project.settings', 'project.team', 'project.modules' => false,
            default => $this->checkNonCore($project, $ability),
        };
    }

    /**
     * A module is available to the project when the instance admin provided
     * it and the project enabled it (spec §5, two steps).
     */
    public function moduleAvailable(Project $project, string $moduleId): bool
    {
        if (! ModuleRegistry::has($moduleId)) {
            return false;
        }

        return $this->moduleStates($project)[$moduleId] ?? false;
    }

    /**
     * Modules available to the project, in lifecycle order — the board
     * columns and module navigation are built from this.
     *
     * @return list<Module>
     */
    public function availableModules(Project $project): array
    {
        return array_values(array_filter(
            ModuleRegistry::all(),
            fn (Module $module) => $this->moduleStates($project)[$module->id] ?? false,
        ));
    }

    /**
     * Drop the memoized availability — after module_states mutations
     * (the enablement UI of 004) within the same request.
     */
    public function flushMemo(): void
    {
        $this->moduleMemo = [];
    }

    /**
     * @return array<string, bool> module id => provided AND enabled
     */
    private function moduleStates(Project $project): array
    {
        if (! isset($this->moduleMemo[$project->id])) {
            // a fresh query, not the relation cache: availability mutations
            // followed by flushMemo() must be visible within the request
            $this->moduleMemo[$project->id] = $project->moduleStates()
                ->get()
                ->mapWithKeys(fn (ModuleState $state): array => [
                    $state->module => $state->provided_by_admin && $state->enabled_by_org,
                ])
                ->all();
        }

        return $this->moduleMemo[$project->id];
    }

    /**
     * Board and module abilities of a plain member.
     */
    private function checkNonCore(Project $project, string $ability): bool
    {
        // The board is the member workspace (spec §6): any verb, any member.
        if (str_starts_with($ability, 'board.')) {
            return true;
        }

        // Module ability '<module>.<section>.<verb>': the module must be
        // known to the registry and available; at the v2 start every member
        // holds all module verbs (v2-17). Anything that is not a full
        // module.section.verb triple grants nothing.
        if (substr_count($ability, '.') !== 2) {
            return false;
        }

        [$moduleId] = explode('.', $ability, 2);

        return $this->moduleAvailable($project, $moduleId);
    }
}
