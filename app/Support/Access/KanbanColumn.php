<?php

namespace App\Support\Access;

/**
 * A kanban board column a module declares (spec §6): the board is assembled
 * from the module columns in lifecycle order. Live since 005 — tasks
 * reference them as '<module>.<column>'; the qa draft set (intake/verify/
 * done) is retuned by 002, so ids are sticky once stored.
 */
final class KanbanColumn
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
    ) {}
}
