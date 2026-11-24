<?php

namespace App\Support\Access;

/**
 * A kanban board column a module declares (spec §6): the board is assembled
 * from the columns of enabled modules in lifecycle order. Reserved for the
 * board task (002) — descriptors start with none.
 */
final class KanbanColumn
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
    ) {}
}
