<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

/** "Settings → API keys" page: all project keys. */
class SettingsKeysPageController extends Controller
{
    public function __invoke(Project $project): View
    {
        $this->authorize('update', $project);

        return view('settings.keys', ['project' => $project]);
    }
}
