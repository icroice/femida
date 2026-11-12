<?php

namespace App\Mcp\Concerns;

use App\Models\ApiKey;
use App\Models\Project;
use App\Models\Run;
use App\Models\SessionCase;
use App\Models\TestCase;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

/**
 * Access to the project key context inside MCP tools.
 * The attributes are set by AuthenticateApiKey on the current HTTP request.
 */
trait ResolvesApiKeyContext
{
    /**
     * An error meant for the agent: abort()/JsonRpcException inside tools are
     * swallowed into an "internal error" (depending on app.debug), while the
     * MCP layer surfaces a ValidationException as text — so we use that.
     */
    protected function fail(string $message, string $key = 'input'): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }

    protected function apiKey(): ApiKey
    {
        return request()->attributes->get('apiKey');
    }

    protected function project(): Project
    {
        return request()->attributes->get('apiProject');
    }

    protected function owner(): ?User
    {
        return request()->attributes->get('apiUser');
    }

    /**
     * A required integer tool argument: a missing or non-numeric value gives
     * the agent an explicit field error, not a "Case #0 not found" after
     * null is cast to zero.
     */
    protected function requiredInt(Request $request, string $key): int
    {
        $value = $request->get($key);

        if ($value === null || $value === '') {
            $this->fail("Missing required field '{$key}'", $key);
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->fail("Field '{$key}' must be an integer", $key);
        }

        return (int) $value;
    }

    protected function caseOrFail(int $id): TestCase
    {
        $case = TestCase::query()->find($id);
        if ($case === null || ! $case->project->is($this->project())) {
            $this->fail("Case #{$id} not found");
        }

        return $case;
    }

    protected function sessionOrFail(int $id): TestSession
    {
        $session = TestSession::query()->find($id);
        if ($session === null || ! $session->project->is($this->project())) {
            $this->fail("Session #{$id} not found");
        }

        return $session;
    }

    protected function runOrFail(int $id): Run
    {
        $run = Run::query()->find($id);
        if ($run === null || ! $run->session->project->is($this->project())) {
            $this->fail("Run #{$id} not found");
        }

        return $run;
    }

    /**
     * @return array{0: TestSession, 1: SessionCase}
     */
    protected function sessionCaseOrFail(int $sessionId, int $caseId): array
    {
        $session = $this->sessionOrFail($sessionId);

        $sessionCase = SessionCase::query()
            ->where('session_id', $session->id)
            ->where('case_id', $caseId)
            ->first();
        if ($sessionCase === null) {
            $this->fail('Case is not attached to this session');
        }

        return [$session, $sessionCase];
    }
}
