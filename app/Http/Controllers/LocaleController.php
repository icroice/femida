<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LocaleController
{
    public function switch(string $locale, Request $request): RedirectResponse
    {
        $locale = in_array($locale, ['ru', 'en'], true) ? $locale : 'en';
        $request->session()->put('locale', $locale);

        if ($request->user()) {
            /** @var User $user */
            $user = $request->user();
            $user->forceFill(['locale' => $locale])->save();
        }

        App::setLocale($locale);

        return back();
    }
}
