<?php

namespace App\Enums;

/**
 * Project roles. The v2 start set (decision v2-17): admin — settings, team,
 * modules; developer — the board and the module functionality. Module
 * business roles are a reserved extension (App\Support\Access\ModuleRole).
 */
enum ProjectRole: string
{
    case Admin = 'admin';
    case Developer = 'developer';
}
