<?php

namespace App\Http\Controllers\Api;

use App\Enums\StepStatus;
use App\Exceptions\ScreenshotStorageException;
use App\Http\Controllers\Api\Concerns\InteractsWithApiKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreStepRequest;
use App\Models\Run;
use App\Models\SessionCase;
use App\Services\ScreenshotService;
use App\Services\SessionFlowService;
use Illuminate\Http\JsonResponse;

class StepResultController extends Controller
{
    use InteractsWithApiKey;

    public function __construct(
        private readonly ScreenshotService $screenshots,
        private readonly SessionFlowService $flow,
    ) {}

    /**
     * Step result + screenshot (multipart). Holes (failed/error/skipped/
     * empty) are filled by resubmission; a passed step is frozen:
     * resubmitting a passed step → 422.
     */
    public function store(StoreStepRequest $request, Run $run): JsonResponse
    {
        $this->ensureProjectScope($request, $run->session->project);

        $sessionCase = SessionCase::query()
            ->where('session_id', $run->session_id)
            ->where('case_id', $request->integer('case_id'))
            ->first();
        abort_unless($sessionCase !== null, 422, 'Case is not attached to this session');

        $file = $request->file('screenshot');
        $bytes = $file !== null
            ? $this->screenshots->readUpload($file)
            : null;
        $extension = $file !== null
            ? strtolower($file->getClientOriginalExtension() ?: 'png')
            : 'png';

        try {
            $result = $this->flow->submitStep(
                $run,
                $sessionCase,
                $request->integer('step_index'),
                StepStatus::from($request->string('status')->toString()),
                $request->input('note'),
                $request->input('elapsed_ms'),
                $bytes,
                $extension,
            );
        } catch (ScreenshotStorageException $e) {
            // Storage failure stays a loud 500 with the text;
            // the explicit type replaces the old blanket HttpException catch.
            abort(500, $e->getMessage());
        }

        $step = $result['step'];
        $shot = $result['screenshot'];

        return response()->json([
            'data' => [
                'run_id' => $run->id,
                'case_id' => $sessionCase->case_id,
                'step_index' => $step->step_index,
                'status' => $step->status->value,
                'note' => $step->note,
                'elapsed_ms' => $step->elapsed_ms,
                'screenshot' => $shot !== null
                    ? ['id' => $shot->id, 'width' => $shot->width, 'height' => $shot->height, 'bytes' => $shot->bytes]
                    : null,
            ],
        ], 201);
    }
}
