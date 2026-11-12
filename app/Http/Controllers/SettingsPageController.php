<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

class SettingsPageController extends Controller
{
    public function __invoke(Project $project): View
    {
        $this->authorize('update', $project);

        return view('settings.page', ['project' => $project]);
    }
}
