<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The "Users" section: the admin sees everyone, a project owner sees the
 * members of their projects. Creating users and the role editor; password
 * reset and blocking — admin only. An owner does not appoint owners.
 */
class UsersAdmin extends FemidaComponent
{
    public string $name = '';

    public string $email = '';

    public string $locale = 'en';

    public string $password = '';

    /** @var array<int, string> project_id => owner|developer|tester|'' */
    public array $roles = [];

    public ?string $generatedPassword = null;

    public ?string $generatedFor = null;

    /**
     * Projects managed by the current user (admin — all).
     *
     * @return Collection<int, Project>
     */
    public function manageableProjects(): Collection
    {
        $query = Project::query()->orderBy('name');

        if (! auth()->user()->isAdmin()) {
            $query->whereIn('id', auth()->user()->ownedProjectIds());
        }

        return $query->get();
    }

    /**
     * Roles the current user may assign.
     *
     * @return list<string>
     */
    public function assignableRoles(): array
    {
        // only the admin appoints owners
        return auth()->user()->isAdmin()
            ? ['owner', 'developer', 'tester']
            : ['developer', 'tester'];
    }

    public function createUser(): void
    {
        $this->authorize('create', User::class);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'locale' => ['required', 'in:ru,en'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = null;
        try {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'locale' => $data['locale'],
                'password' => $data['password'],
                'email_verified_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // TOCTOU: the name was taken between validation and insert — 409 semantics
            // as a form error, not a 500
            $this->addError('name', __('users.name_taken'));

            return;
        }

        $this->syncRoles($user, $this->roles);

        $this->reset(['name', 'email', 'password', 'roles']);

        $this->dispatch('notify', message: __('users.created'));
    }

    /**
     * The role of an existing user in a project: change, add
     * ('' — remove access).
     */
    public function setMemberRole(int $userId, int $projectId, string $role): void
    {
        $this->authorize('create', User::class);

        $user = User::query()->findOrFail($userId);
        $this->assertManageable($projectId);

        if ($role === '') {
            $user->projects()->detach($projectId);

            return;
        }

        if (! in_array($role, $this->assignableRoles(), true)) {
            return;
        }

        $user->projects()->syncWithoutDetaching([$projectId => ['role' => $role]]);
    }

    public function resetPassword(int $userId): void
    {
        $this->authorize('resetPassword', User::query()->findOrFail($userId));

        $user = User::query()->findOrFail($userId);
        $password = Str::password(14, symbols: false);

        $user->forceFill(['password' => Hash::make($password)])->save();

        // a password reset invalidates other sessions and remember cookies
        $this->killSessions($user);

        $this->generatedPassword = $password;
        $this->generatedFor = $user->name;
    }

    public function toggleBlock(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('block', $user);

        if ($user->isBlocked()) {
            $user->unblock();

            return;
        }

        $user->block();

        // blocking tears down active sessions immediately, without waiting for the next request
        $this->killSessions($user);
    }

    /**
     * Kill a user's active sessions: database driver — wipe the table,
     * null the remember cookie via the token in users.remember_token.
     */
    private function killSessions(User $user): void
    {
        $user->forceFill(['remember_token' => null])->saveQuietly();

        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }

    public function render(): View
    {
        $projectIds = $this->manageableProjects()->pluck('id');

        $users = User::query()
            // an owner sees only members of their projects; the admin sees everyone
            ->when($projectIds->isNotEmpty() && ! auth()->user()->isAdmin(), fn ($q) => $q
                ->whereHas('projects', fn ($pq) => $pq->whereIn('projects.id', $projectIds)))
            ->with(['projects' => fn ($q) => auth()->user()->isAdmin()
                ? $q
                : $q->whereIn('projects.id', $projectIds)])
            ->orderBy('name')
            ->get();

        return view('livewire.users-admin', [
            'users' => $users,
            'projects' => $this->manageableProjects(),
            'assignableRoles' => $this->assignableRoles(),
        ])->title(__('app.users'));
    }

    /**
     * Roles on creation: only managed projects and allowed roles.
     *
     * @param  array<int, string>  $roles
     */
    private function syncRoles(User $user, array $roles): void
    {
        foreach ($roles as $projectId => $role) {
            if (! in_array($role, $this->assignableRoles(), true)) {
                continue;
            }

            try {
                $this->assertManageable((int) $projectId);
            } catch (AuthorizationException) {
                continue;
            }

            $user->projects()->syncWithoutDetaching([(int) $projectId => ['role' => $role]]);
        }
    }

    /** The project must be manageable by the current user. */
    private function assertManageable(int $projectId): void
    {
        if (! $this->manageableProjects()->pluck('id')->contains($projectId)) {
            abort(403);
        }
    }
}
