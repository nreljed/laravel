<?php

namespace Nreljed\MarketingModule\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nreljed\MarketingModule\Database\Factories\WorkflowRunFactory;
use Nreljed\MarketingModule\Enums\WorkflowRunStatus;

/**
 * One execution of a workflow, kept so an operator can see why an automation
 * did (or did not) touch a contact without reading server logs.
 */
class WorkflowRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'step_index',
        'event',
        'subject_type',
        'subject_id',
        'status',
        'message',
        'payload',
        'ran_at',
    ];

    protected $casts = [
        'step_index' => 'integer',
        'status' => WorkflowRunStatus::class,
        'payload' => 'array',
        'ran_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return WorkflowRunFactory::new();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
