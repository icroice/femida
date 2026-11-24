<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\Shell\AppShell;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function create(): View
    {
        return view('projects.create');
    }

    public function store(Request $request, AppShell $shell): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required', 'string', 'max:60',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('projects', 'slug'),
            ],
            // The JIRA-style key (005): task numbers are "{key}-{n}". Immutable.
            'key' => [
                'required', 'string', 'min:2', 'max:10',
                'regex:/^[A-Z]+$/',
                Rule::unique('projects', 'key'),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // A parallel request may claim the slug or the key between validation
        // and insert; the DB unique indexes are the source of truth — surface
        // the violated one as a form error
        try {
            $project = Project::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'key' => $data['key'],
                'description' => $data['description'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            $field = Project::query()->where('key', $data['key'])->exists() ? 'key' : 'slug';

            return back()->withInput()->withErrors([$field => __('app.'.$field.'_taken')]);
        }

        return redirect()
            ->to($shell->landingUrl($project) ?? route('home'))
            ->with('status', __('app.project_created', ['name' => $project->name]));
    }
}
