<?php

namespace Nreljed\MarketingModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nreljed\MarketingModule\Enums\WorkflowAction;
use Nreljed\MarketingModule\Enums\WorkflowTrigger;
use Nreljed\MarketingModule\Models\Workflow;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => null,
            'trigger' => WorkflowTrigger::ContactCreated,
            'action' => WorkflowAction::AddTag,
            'condition_tag_id' => null,
            'tag_id' => null,
            'contact_status' => null,
            'webhook_url' => null,
            'is_active' => true,
            'run_count' => 0,
            'last_run_at' => null,
            'metadata' => [],
        ];
    }
}
