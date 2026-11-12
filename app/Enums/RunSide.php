<?php

namespace App\Enums;

/**
 * Run side: the head commit ("after") or the base commit ("before").
 */
enum RunSide: string
{
    case Before = 'before';
    case After = 'after';
}
