<?php

namespace App\Support\Access;

/**
 * A business role a module brings (spec §5): full slug is '<module>.<slug>'.
 * Reserved extension point — empty in all descriptors at the v2 start
 * (decision v2-17): project roles are just admin/developer, and module
 * verbs are granted to every project member. When module roles arrive,
 * AccessService starts consulting them without changing its API.
 *
 * @phpstan-type Permissions array<string, list<Permission>>
 */
final class ModuleRole
{
    /**
     * @param  Permissions  $permissions  section => verbs of this role
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly array $permissions = [],
    ) {}

    public function allows(string $section, Permission $verb): bool
    {
        return in_array($verb, $this->permissions[$section] ?? [], true);
    }
}
