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

    /** Projects where the user is a project admin (for the Users section pages). */
    /** @return BelongsToMany<Project, $this, ProjectUser, 'pivot'> */
    public function administeredProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->using(ProjectUser::class)
            ->wherePivot('role', ProjectRole::Admin->value);
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

    /**
     * Project admin: settings, team, modules (v2-17). There may be several;
     * the instance admin passes too.
     */
    public function isProjectAdmin(Project $project): bool
    {
        return $this->isAdmin() || $this->roleIn($project) === ProjectRole::Admin;
    }

    /** Can open the "Users" section: admin or admin of at least one project. */
    public function managesUsers(): bool
    {
        return $this->isAdmin() || $this->administeredProjects()->exists();
    }

    /**
     * Ids of projects the user administers.
     *
     * @return list<int>
     */
    public function administeredProjectIds(): array
    {
        return $this->administeredProjects()->pluck('projects.id')->all();
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
