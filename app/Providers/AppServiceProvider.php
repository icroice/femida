<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
