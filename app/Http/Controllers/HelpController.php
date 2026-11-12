<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HelpController extends Controller
{
    /** The in-app handbook: section keys double as the rail navigation order. */
    public const SECTIONS = ['overview', 'quick-start', 'case-library', 'sessions', 'agent', 'roles'];

    public function index(): RedirectResponse
    {
        return redirect()->route('help.show', self::SECTIONS[0]);
    }

    public function show(string $section): View
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);

        $next = self::SECTIONS[array_search($section, self::SECTIONS, true) + 1] ?? null;

        return view('help.show', [
            'section' => $section,
            'sections' => self::SECTIONS,
            'next' => $next,
        ]);
    }
}
