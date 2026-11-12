<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 *
 * The factory writes a random hash (not for authentication).
 * Create real keys via ApiKey::issue().
 */
class ApiKeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'name' => 'dev-'.fake()->lexify('???'),
            'token_hash' => hash('sha256', Str::random(40)),
            'token_prefix' => 'fem_'.fake()->lexify('???').'…',
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
