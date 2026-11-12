<?php

namespace Database\Factories;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Models\Project;
use App\Models\TestCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestCase>
 */
class TestCaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => 'Partial cancellation: recalculating the receipt total',
            'preconditions' => 'The system has an order with several line items, paid by card.',
            'steps' => [
                ['action' => 'Open the order and click "Cancel part of the items"', 'expected' => 'the cancel panel opens'],
                ['action' => 'Remove one item and confirm the cancellation', 'expected' => 'the item is removed, the confirmation is accepted'],
                ['action' => 'Check the receipt total', 'expected' => 'the total is recalculated, a refund line appears'],
            ],
            'priority' => CasePriority::P1,
            'status' => CaseStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => CaseStatus::Inactive]);
    }

    public function priority(CasePriority $priority): static
    {
        return $this->state(fn () => ['priority' => $priority]);
    }
}
