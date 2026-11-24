<?php

namespace App\Support\Access;

/**
 * The module registry (spec §2): a static catalog of module descriptors,
 * synchronous with the code. The database stores no modules — modules are
 * available to every project member (decision v2-18).
 */
final class ModuleRegistry
{
    /** @var array<string, Module>|null */
    private static ?array $modules = null;

    /**
     * @return list<Module> in lifecycle order
     */
    public static function all(): array
    {
        $modules = array_values(self::load());

        usort($modules, fn (Module $a, Module $b) => $a->order <=> $b->order);

        return $modules;
    }

    public static function find(string $id): ?Module
    {
        return self::load()[$id] ?? null;
    }

    public static function has(string $id): bool
    {
        return isset(self::load()[$id]);
    }

    /**
     * A module title for display; an id dropped from the registry shows
     * as-is — no 500 (v2-18: the registry is code-only, nothing persists
     * module ids anymore, but stale refs must not crash rendering).
     */
    public static function title(string $id): string
    {
        return self::load()[$id]->title ?? $id;
    }

    /**
     * Does a board column ref exist? The ref format is '<module>.<column>'
     * (005): tasks reference columns declared by modules in code.
     */
    public static function hasColumn(string $ref): bool
    {
        [$moduleId, $columnId] = array_pad(explode('.', $ref, 2), 2, null);
        $module = self::load()[$moduleId] ?? null;

        if ($module === null) {
            return false;
        }

        foreach ($module->columns as $column) {
            if ($column->id === $columnId) {
                return true;
            }
        }

        return false;
    }

    /**
     * The ability string '<module>.<section>.<verb>' for a check site
     * (fail-loud): a module id or section lost to a registry change must
     * crash here, at the call site, not silently widen or narrow access.
     */
    public static function ability(string $moduleId, string $section, Permission $verb): string
    {
        $module = self::load()[$moduleId]
            ?? throw new \LogicException("Unknown module '{$moduleId}' in the registry");

        if (! $module->hasSection($section)) {
            throw new \LogicException("Module '{$moduleId}' declares no section '{$section}'");
        }

        return $module->ability($section, $verb);
    }

    /**
     * @internal resets the static cache (after registry changes in tests)
     */
    public static function flush(): void
    {
        self::$modules = null;
    }

    /**
     * @return array<string, Module>
     */
    private static function load(): array
    {
        if (self::$modules !== null) {
            return self::$modules;
        }

        $list = [
            // The qa module — the v1 functionality (spec §4, decision v2-7).
            // Roles/tools declarations are reserved extension points
            // (v2-17): they arrive with 011/013. The draft board columns
            // (intake → verify → done) came with 005; 002 retunes them.
            new Module(
                id: 'qa',
                title: 'QA',
                order: 500,
                sections: ['cases', 'sessions'],
                columns: [
                    new KanbanColumn(id: 'intake', title: 'Intake'),
                    new KanbanColumn(id: 'verify', title: 'Verify'),
                    new KanbanColumn(id: 'done', title: 'Done'),
                ],
            ),
        ];

        $map = [];

        foreach ($list as $module) {
            $map[$module->id] = $module;
        }

        return self::$modules = $map;
    }
}
