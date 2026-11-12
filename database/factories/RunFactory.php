<?php

namespace Database\Factories;

use App\Enums\RunSide;
use App\Enums\RunStatus;
use App\Models\Run;
use App\Models\TestSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Run>
 */
class RunFactory extends Factory
{
    public function definition(): array
    {
        $session = TestSession::factory();

        return [
            'session_id' => $session,
            'side' => RunSide::After,
            'commit' => fake()->sha1(),
            'status' => RunStatus::Done,
            'error' => null,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now(),
        ];
    }

    public function side(RunSide $side): static
    {
        return $this->state(function (array $attributes) use ($side) {
            $session = TestSession::find($attributes['session_id'] ?? null);

            return [
                'side' => $side,
                'commit' => $side === RunSide::Before
                    ? $session?->base_commit ?? fake()->sha1()
                    : $session?->head_commit ?? fake()->sha1(),
            ];
        });
    }

    public function failed(string $error = 'The app did not start: ready timeout'): static
    {
        return $this->state(fn () => [
            'status' => RunStatus::Failed,
            'error' => $error,
        ]);
    }
}
