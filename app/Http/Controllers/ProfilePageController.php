<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/** "My profile" page: name, email, locale, password change. */
class ProfilePageController extends Controller
{
    public function __invoke(): View
    {
        return view('profile.page');
    }
}
