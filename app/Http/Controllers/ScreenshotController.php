<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Screenshot;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScreenshotController extends Controller
{
    /**
     * Screenshots are served to project members only — nothing leaks outside.
     */
    public function show(Project $project, Screenshot $screenshot): BinaryFileResponse
    {
        abort_unless($screenshot->stepResult->sessionCase->session->project->is($project), 404);
        $this->authorize('view', $project);

        $path = $screenshot->absolutePath();
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $this->mimeType($path),
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function mimeType(string $path): string
    {
        return match (pathinfo($path, PATHINFO_EXTENSION)) {
            'jpg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }
}
