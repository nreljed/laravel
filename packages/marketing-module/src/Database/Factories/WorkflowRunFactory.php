<?php

namespace Nreljed\MarketingModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nreljed\MarketingModule\Enums\WorkflowRunStatus;
use Nreljed\MarketingModule\Models\Workflow;
use Nreljed\MarketingModule\Models\WorkflowRun;

/**
 * @extends Factory<WorkflowRun>
 */
class WorkflowRunFactory extends Factory
{
    protected $model = WorkflowRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'event' => 'contact_created',
            'subject_type' => null,
            'subject_id' => null,
            'status' => WorkflowRunStatus::Success,
            'message' => null,
            'payload' => [],
            'ran_at' => now(),
        ];
    }
}
