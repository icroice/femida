<?php

namespace App\Support\Access;

/**
 * The module registry (spec §2): a static catalog of module descriptors,
 * synchronous with the code. The database stores no modules — only
 * per-project availability rows (module_states).
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
     * A module title for display; an id dropped from the registry
     * (stale availability rows in the database) shows as-is — no 500.
     */
    public static function title(string $id): string
    {
        return self::load()[$id]->title ?? $id;
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
            // Roles/columns/tools declarations are reserved extension points
            // (v2-17): they arrive with 002/011/013.
            new Module(
                id: 'qa',
                title: 'QA',
                order: 500,
                sections: ['cases', 'sessions'],
            ),
        ];

        $map = [];

        foreach ($list as $module) {
            $map[$module->id] = $module;
        }

        return self::$modules = $map;
    }
}
