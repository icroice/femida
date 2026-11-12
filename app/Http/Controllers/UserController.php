<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with(['projects'])
            ->orderBy('name')
            ->get();

        return view('users.index', [
            'users' => $users,
            'projects' => Project::query()->orderBy('name')->get(),
        ]);
    }
}
