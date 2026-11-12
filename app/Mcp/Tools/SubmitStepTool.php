<?php

namespace App\Mcp\Tools;

use App\Enums\StepStatus;
use App\Exceptions\ScreenshotStorageException;
use App\Mcp\Concerns\ResolvesApiKeyContext;
use App\Models\SessionCase;
use App\Services\ScreenshotService;
use App\Services\SessionFlowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('submit_step')]
#[Description('Result of one case step + screenshot (base64, PNG/JPEG/WebP, up to 5 MB). Fills an empty step or overwrites a failed/error/skipped one (e.g. a flaky retry). A passed step is frozen — resubmission is rejected. skipped/error require a note — the reason the step has no result/frame.')]
class SubmitStepTool extends Tool
{
    use ResolvesApiKeyContext;

    public function __construct(private readonly SessionFlowService $flow) {}

    public function handle(Request $request): Response
    {
        $run = $this->runOrFail($this->requiredInt($request, 'run_id'));

        $data = $request->validate([
            'case_id' => ['required', 'integer'],
            'step_index' => ['required', 'integer', 'min:1', 'max:99'],
            'status' => ['required', 'in:passed,failed,skipped,error'],
            'note' => ['required_if:status,skipped,error', 'nullable', 'string', 'max:2000'],
            'elapsed_ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
            'screenshot_base64' => ['nullable', 'string'],
        ]);

        $sessionCase = SessionCase::query()
            ->where('session_id', $run->session_id)
            ->where('case_id', $data['case_id'])
            ->first();
        if ($sessionCase === null) {
            $this->fail('Case is not attached to this session');
        }

        $bytes = ! empty($data['screenshot_base64'])
            ? $this->decodeScreenshot($data['screenshot_base64'])
            : null;

        try {
            $result = $this->flow->submitStep(
                $run,
                $sessionCase,
                (int) $data['step_index'],
                StepStatus::from($data['status']),
                $data['note'] ?? null,
                $data['elapsed_ms'] ?? null,
                $bytes,
            );
        } catch (ScreenshotStorageException $e) {
            // Disk write failure: the text reaches the agent instead of a
            // generic "internal error". Contract errors of the
            // flow service are ValidationException and propagate on their own.
            $this->fail($e->getMessage());
        }

        $step = $result['step'];
        $shot = $result['screenshot'];

        return Response::json([
            'run_id' => $run->id,
            'case_id' => (int) $data['case_id'],
            'step_index' => $step->step_index,
            'status' => $step->status->value,
            'note' => $step->note,
            'elapsed_ms' => $step->elapsed_ms,
            'screenshot' => $shot !== null
                ? ['id' => $shot->id, 'width' => $shot->width, 'height' => $shot->height, 'bytes' => $shot->bytes]
                : null,
        ]);
    }

    /**
     * Accepts both plain base64 and a data URI (data:image/png;base64,...).
     */
    private function decodeScreenshot(string $input): string
    {
        if (preg_match('#^data:image/[a-z+]+;base64,#i', $input, $m) === 1) {
            $input = substr($input, strlen($m[0]));
        }

        // length pre-check: do not allocate memory for input that obviously
        // exceeds the limit (base64 → bytes ≈ 3/4 of the length)
        if (intdiv(strlen($input), 4) * 3 > ScreenshotService::MAX_BYTES) {
            $this->fail('screenshot_base64 exceeds the 5 MB limit');
        }

        $bytes = base64_decode($input, true);
        if ($bytes === false || $bytes === '') {
            $this->fail('screenshot_base64 is not valid base64');
        }

        return $bytes;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => Type::integer()->required(),
            'case_id' => Type::integer()->required(),
            'step_index' => Type::integer()->required()->min(1)->max(99),
            'status' => Type::string()->enum(['passed', 'failed', 'skipped', 'error'])->required(),
            'note' => Type::string()->max(2000)->description('Short note, e.g. the total was not recalculated. Required for skipped/error — the reason for skipping or failing'),
            'elapsed_ms' => Type::integer()->min(0)->max(3600000)->description('How long the step took, ms (case duration is summed across steps)'),
            'screenshot_base64' => Type::string()->description('Step screenshot: base64 (a data URI works too), PNG/JPEG/WebP, up to 5 MB'),
        ];
    }
}
