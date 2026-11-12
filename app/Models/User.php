<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ProjectRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'locale', 'theme'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @return BelongsToMany<Project, $this, ProjectUser, 'pivot'> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->using(ProjectUser::class)
            ->withPivot(['role']);
    }

    /** Projects where the user is an owner (for the admin's Users section pages). */
    /** @return BelongsToMany<Project, $this, ProjectUser, 'pivot'> */
    public function ownedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->using(ProjectUser::class)
            ->wherePivot('role', ProjectRole::Owner->value);
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function block(): void
    {
        $this->forceFill(['blocked_at' => now()])->save();
    }

    public function unblock(): void
    {
        $this->forceFill(['blocked_at' => null])->save();
    }

    /** The user's role in a project (null — not a member). */
    public function roleIn(Project $project): ?ProjectRole
    {
        return $this->projects->firstWhere('id', $project->id)?->pivot?->role;
    }

    /** A member of the project (admin — member of any). */
    public function isMember(Project $project): bool
    {
        return $this->isAdmin() || $this->roleIn($project) !== null;
    }

    /** developer-level rights in a project (owner and admin — everywhere in theirs). */
    public function isDeveloper(Project $project): bool
    {
        return $this->isOwner($project)
            || $this->roleIn($project) === ProjectRole::Developer;
    }

    /** Project owner: settings, project team (admin — everywhere). */
    public function isOwner(Project $project): bool
    {
        return $this->isAdmin() || $this->roleIn($project) === ProjectRole::Owner;
    }

    /** Can open the "Users" section: admin or owner of at least one project. */
    public function managesUsers(): bool
    {
        return $this->isAdmin() || $this->ownedProjects()->exists();
    }

    /**
     * Ids of projects the user owns.
     *
     * @return list<int>
     */
    public function ownedProjectIds(): array
    {
        return $this->ownedProjects()->pluck('projects.id')->all();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'blocked_at' => 'datetime',
        ];
    }
}
