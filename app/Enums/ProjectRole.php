<?php

namespace App\Enums;

enum ProjectRole: string
{
    case Owner = 'owner';
    case Developer = 'developer';
    case Tester = 'tester';
}
