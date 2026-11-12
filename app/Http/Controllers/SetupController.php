<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * First-run wizard: environment checks → admin account.
 * Closed forever once the first user exists (middleware).
 */
class SetupController extends Controller
{
    public function index(Request $request): View
    {
        return view('setup.wizard', [
            'checks' => $this->environmentChecks($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'locale' => ['required', 'in:ru,en'],
        ], [], [
            'name' => __('setup.attributes.name'),
            'email' => __('setup.attributes.email'),
            'password' => __('setup.attributes.password'),
            'locale' => __('setup.attributes.locale'),
        ]);

        // race of two parallel POSTs: the first user created closes the wizard
        // forever. Transaction + a re-check (SQLite serializes writes) — the
        // second POST rolls back instead of creating a second admin.
        $user = DB::transaction(function () use ($data) {
            abort_if(User::query()->exists(), 409, 'Setup is already completed');

            $user = User::query()->create($data);
            $user->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('home')
            ->with('status', __('setup.done', ['name' => $user->name]));
    }

    /**
     * @return array<int, array{label: string, ok: bool}>
     */
    private function environmentChecks(Request $request): array
    {
        $dbOk = false;
        try {
            Artisan::call('migrate:status'); // opens the DB connection
            $dbOk = true;
        } catch (\Throwable) {
            $dbOk = false;
        }

        return [
            ['label' => __('setup.checks.php'), 'ok' => version_compare(PHP_VERSION, '8.3.0', '>=')],
            ['label' => __('setup.checks.db'), 'ok' => $dbOk],
            ['label' => __('setup.checks.storage'), 'ok' => is_writable(storage_path('app')) && is_writable(storage_path('framework'))],
            ['label' => __('setup.checks.key'), 'ok' => (bool) config('app.key')],
        ];
    }
}
