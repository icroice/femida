<?php

namespace App\Enums;

enum StepStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Error = 'error';
}
