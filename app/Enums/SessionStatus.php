<?php

namespace App\Enums;

/**
 * Session status: two values — "passed" / "not passed".
 * A session starts as not passed; it is marked "passed" automatically once
 * coverage is fully green (SessionFlowService::reevaluate); any reset
 * or a reject verdict brings it back to "not passed". Intermediate states
 * ("running", "awaiting review") are not stored — they are derived from runs
 * (TestSession::flowState()).
 */
enum SessionStatus: string
{
    case NotPassed = 'not_passed';
    case Passed = 'passed';
}
