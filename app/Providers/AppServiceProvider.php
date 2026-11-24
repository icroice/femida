<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\User;
use App\Support\Access\AccessService;
use App\Support\Shell\AppShell;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The instance admin passes any check before entering a policy
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

        // The Gate bridge: abilities are checked by AccessService only —
        // Gate/Livewire/Blade are transport. Usage:
        //   $user->can('access', [$project, 'qa.cases.update'])
        Gate::define('access', function (User $user, Project $project, string $ability): bool {
            return $this->app->make(AccessService::class)->check($user, $project, $ability);
        });

        // The app shell model for the layout (006): assembled in one
        // presenter class, Blade stays display-only. The home picker
        // shares it — the landing link policy lives in one place.
        View::composer(['layouts.app', 'home'], fn (\Illuminate\View\View $view) => $view->with(
            'shell', $this->app->make(AppShell::class)
        ));

        // A light API rate limit: per project key, per IP for guests
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)->by(
            $request->bearerToken() ?? $request->ip()
        ));

        // Web login: 5 attempts per minute per email+IP pair — brute-force protection
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip()
        ));
    }
}
