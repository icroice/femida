<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * The list of projects available to the user (admin — all).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Project::query()
            ->withCount(['testCases', 'testSessions'])
            ->orderBy('name');

        if (! $user->isAdmin()) {
            $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
        }

        return view('home', [
            'projects' => $query->get(),
        ]);
    }
}
