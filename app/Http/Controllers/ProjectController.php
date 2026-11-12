<?php

namespace App\Http\Controllers;

use App\Models\Project;
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required', 'string', 'max:60',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('projects', 'slug'),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // A parallel request may claim the slug between validation and insert;
        // the DB unique index is the source of truth — surface it as a form error
        try {
            $project = Project::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors(['slug' => __('app.slug_taken')]);
        }

        return redirect()
            ->route('projects.cases.index', $project)
            ->with('status', __('app.project_created', ['name' => $project->name]));
    }
}
