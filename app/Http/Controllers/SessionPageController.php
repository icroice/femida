<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TestSession;
use Illuminate\View\View;

class SessionPageController extends Controller
{
    public function show(Project $project, TestSession $session): View
    {
        // the model must belong to the project from the URL (same as SessionReview::mount)
        abort_unless($session->project->is($project), 404);
        $this->authorize('view', $session);

        return view('sessions.show', ['project' => $project, 'session' => $session]);
    }

    // the session list is a Livewire browser with filters in the section bar
    public function index(Project $project): View
    {
        return view('sessions.index', ['project' => $project]);
    }
}
