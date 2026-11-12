<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TestCase;
use Illuminate\View\View;

class TestCasePageController extends Controller
{
    public function create(Project $project): View
    {
        $this->authorize('create', [TestCase::class, $project]);

        return view('cases.form', ['project' => $project, 'case' => null]);
    }

    public function edit(Project $project, TestCase $case): View
    {
        // the model must belong to the project from the URL, not merely be accessible
        abort_unless($case->project->is($project), 404);
        $this->authorize('update', $case);

        return view('cases.form', ['project' => $project, 'case' => $case]);
    }

    // the cases page is a Livewire browser (folder rail + filters in the section bar);
    // the initial state is read from the URL in CasesBrowser (#[Url])
    public function index(Project $project): View
    {
        return view('cases.index', ['project' => $project]);
    }
}
