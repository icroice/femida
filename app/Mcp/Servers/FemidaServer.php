<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\OnboardingResource;
use App\Mcp\Tools\AddCasesToSessionTool;
use App\Mcp\Tools\CreateCaseTool;
use App\Mcp\Tools\CreateSessionTool;
use App\Mcp\Tools\FinishRunTool;
use App\Mcp\Tools\GetCaseTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\GetSessionTool;
use App\Mcp\Tools\ListCasesTool;
use App\Mcp\Tools\SetCaseSuggestionTool;
use App\Mcp\Tools\SetVerdictTool;
use App\Mcp\Tools\StartRunTool;
use App\Mcp\Tools\SubmitSessionMetricsTool;
use App\Mcp\Tools\SubmitStepTool;
use App\Mcp\Tools\UpdateCaseTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Femida')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Femida stores before/after QA evidence for tasks. A typical agent workflow:

1. get_project — validate the key and see which project you are working with.
2. list_cases / get_case — pick the cases affected by the task (match the task
   description and the diff against case titles). Cases are organized into
   folders (folder, up to two levels separated by /) — filter list_cases by folder.
3. create_session — create a session with the selected cases; base_commit is
   required unless only_after mode is on (a quick run without the "before" side).
4. For each side (after first, then before): start_run → for every case
   submit_step for each step (status + screenshot_base64) → finish_run (done or
   failed; finishing moves the session to review once all runs are complete).
5. set_case_suggestion — after the run, suggest a verdict for every case;
   warnings from finish_run and the suggestions summary in get_session show
   which cases still lack one — close them before finishing.
6. submit_session_metrics — at the very end, report token usage for the session
   (tokens_in/tokens_out); step durations are sent with submit_step (elapsed_ms).

A verdict describes the behavior of the head commit; the "before" run is the
evidence baseline. The final verdict is set by a human in the web UI (set_verdict
exists, but by default you do not use it unless the human explicitly asks you
to record their decision).

First time connecting this project? Read the femida://onboarding resource
(resources/list → resources/read): it contains adaptable skeletons of the two
agent skills (femida-write-cases, femida-run-session), the client/driver scripts, a
femida.yaml reference and pitfalls from real runs. Copy the skills into the
project repository and adapt them.
TXT)]
class FemidaServer extends Server
{
    protected array $tools = [
        GetProjectTool::class,
        ListCasesTool::class,
        GetCaseTool::class,
        CreateCaseTool::class,
        UpdateCaseTool::class,
        CreateSessionTool::class,
        GetSessionTool::class,
        AddCasesToSessionTool::class,
        StartRunTool::class,
        FinishRunTool::class,
        SubmitStepTool::class,
        SubmitSessionMetricsTool::class,
        SetCaseSuggestionTool::class,
        SetVerdictTool::class,
    ];

    protected array $resources = [
        OnboardingResource::class,
    ];

    protected array $prompts = [];
}
