<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileKeysPageController;
use App\Http\Controllers\ProfilePageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ScreenshotController;
use App\Http\Controllers\SessionPageController;
use App\Http\Controllers\SettingsKeysPageController;
use App\Http\Controllers\SettingsPageController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TestCasePageController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureProjectMember;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Setup (first run) and login
|--------------------------------------------------------------------------
*/

Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');

    // 5 attempts per minute per email+IP — brute-force protection
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    // only the admin creates projects
    Route::get('/projects/create', [ProjectController::class, 'create'])
        ->middleware('can:create,'.Project::class)
        ->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])
        ->middleware('can:create,'.Project::class)
        ->name('projects.store');

    // the "Users" section — admin only
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('can:viewAny,'.User::class)
        ->name('users.index');

    // the in-app handbook — available to every signed-in user
    Route::get('/help', [HelpController::class, 'index'])->name('help.index');
    Route::get('/help/{section}', [HelpController::class, 'show'])->name('help.show');

    // own profile: name, email, locale, password change; keys on a separate page
    Route::get('/profile', ProfilePageController::class)->name('profile');
    Route::get('/profile/keys', ProfileKeysPageController::class)->name('profile.keys');

    // project context — members only
    Route::prefix('projects/{project:slug}')
        ->middleware(EnsureProjectMember::class)
        ->group(function () {
            Route::get('/', fn (Project $project) => redirect()->route('projects.cases.index', $project))
                ->name('projects.show');

            Route::get('/cases', [TestCasePageController::class, 'index'])
                ->name('projects.cases.index');
            Route::get('/cases/new', [TestCasePageController::class, 'create'])
                ->middleware('can:create,'.TestCase::class.',project')
                ->name('cases.create');
            Route::get('/cases/{case}/edit', [TestCasePageController::class, 'edit'])
                ->name('cases.edit');

            Route::get('/sessions', [SessionPageController::class, 'index'])
                ->name('projects.sessions.index');
            Route::get('/sessions/{session}', [SessionPageController::class, 'show'])
                ->name('sessions.show');

            Route::get('/settings', [SettingsPageController::class, '__invoke'])
                ->name('projects.settings');
            Route::get('/settings/keys', SettingsKeysPageController::class)
                ->name('projects.settings.keys');

            Route::get('/screenshots/{screenshot}', [ScreenshotController::class, 'show'])
                ->name('screenshots.show');
        });
});
