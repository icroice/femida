<?php

namespace App\Models;

use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Machine access at developer level within a single project.
 * An owner is required: runs are attributed to them.
 * The token is shown once; only the sha256 hash is stored.
 */
class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory;

    public const TOKEN_PREFIX = 'fem_';

    protected $fillable = ['project_id', 'user_id', 'name', 'token_hash', 'token_prefix'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Issue a key: returns [model, plaintext token].
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(Project $project, User $owner, string $name): array
    {
        $token = self::TOKEN_PREFIX.Str::random(40);
        $key = self::query()->create([
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'name' => $name,
            'token_hash' => hash('sha256', $token),
            'token_prefix' => substr($token, 0, 8).'…',
        ]);

        return [$key, $token];
    }

    /**
     * Find an active key by its plaintext token.
     */
    public static function findByToken(string $token): ?self
    {
        return self::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->first();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }

    /**
     * The key is usable right now: the owner exists, is not blocked
     * and keeps developer+ rights in the project. Removal from the project,
     * demotion to tester, blocking or deleting the owner
     * all stop the key without a manual revocation.
     */
    public function isUsable(): bool
    {
        $owner = $this->owner;

        return $owner !== null
            && ! $owner->isBlocked()
            && $owner->isDeveloper($this->project);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
