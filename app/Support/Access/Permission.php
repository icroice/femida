<?php

namespace App\Support\Access;

/**
 * Permission verbs over module sections (spec §5): view/create/update/delete
 * cover the section data; settings is a separate right for module
 * configuration. Section and role composition live in the module descriptor.
 */
enum Permission: string
{
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Settings = 'settings';

    /**
     * @return list<string>
     */
    public static function verbs(): array
    {
        return array_map(fn (self $permission) => $permission->value, self::cases());
    }
}
