<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/** "Profile → My API keys" page: issuing and revoking personal keys. */
class ProfileKeysPageController extends Controller
{
    public function __invoke(): View
    {
        return view('profile.keys');
    }
}
