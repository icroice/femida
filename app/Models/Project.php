<?php

namespace App\Models;

use App\Observers\ProjectObserver;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(ProjectObserver::class)]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description'];

    /** @return BelongsToMany<User, $this, ProjectUser, 'pivot'> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ProjectUser::class)
            ->withPivot(['role']);
    }

    /** @return HasMany<ApiKey, $this> */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /** @return HasMany<ModuleState, $this> */
    public function moduleStates(): HasMany
    {
        return $this->hasMany(ModuleState::class);
    }

    /** @return HasMany<TestCase, $this> */
    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class);
    }

    /** @return HasMany<TestSession, $this> */
    public function testSessions(): HasMany
    {
        return $this->hasMany(TestSession::class);
    }
}
