<?php

namespace App\Enums;

/**
 * Verdict for a case in a session: set by a human (tester or developer),
 * or suggested by the agent (agent_suggestion). Semantics: describes head behavior.
 */
enum Verdict: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case Blocked = 'blocked';
}
