<?php

namespace App\Support\Access;

/**
 * A module descriptor from the registry (spec §2): modules live in code,
 * not in the database. A module brings sections with permission verbs,
 * business roles, kanban columns and MCP tools.
 *
 * Sections are the ability namespace: '<module>.<section>.<verb>'.
 */
final class Module
{
    /**
     * @param  list<string>  $sections  section ids of the module
     * @param  list<ModuleRole>  $roles  business roles (reserved, v2-17)
     * @param  list<KanbanColumn>  $columns  board columns (reserved, 002)
     * @param  list<McpTool>  $tools  MCP tools (reserved, 013)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly int $order = 0,
        public readonly array $sections = [],
        public readonly array $roles = [],
        public readonly array $columns = [],
        public readonly array $tools = [],
    ) {}

    public function hasSection(string $section): bool
    {
        return in_array($section, $this->sections, true);
    }

    /**
     * The ability string for a verb in a section of this module.
     */
    public function ability(string $section, Permission $verb): string
    {
        return $this->id.'.'.$section.'.'.$verb->value;
    }
}
