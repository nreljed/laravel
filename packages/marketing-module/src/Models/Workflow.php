<?php

namespace Nreljed\MarketingModule\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nreljed\MarketingModule\Database\Factories\WorkflowFactory;
use Nreljed\MarketingModule\Enums\WorkflowAction;
use Nreljed\MarketingModule\Enums\WorkflowTrigger;

/**
 * One audience or publishing automation.
 *
 * A workflow is deliberately a single trigger -> optional condition -> action
 * rule. Chaining rules is the campaign layer's job, not a hidden graph the
 * operator cannot inspect.
 */
class Workflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'trigger',
        'action',
        'condition_tag_id',
        'tag_id',
        'contact_status',
        'webhook_url',
        'is_active',
        'run_count',
        'last_run_at',
        'metadata',
    ];

    protected $casts = [
        'trigger' => WorkflowTrigger::class,
        'action' => WorkflowAction::class,
        'is_active' => 'boolean',
        'run_count' => 'integer',
        'last_run_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function newFactory(): Factory
    {
        return WorkflowFactory::new();
    }

    public function conditionTag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'condition_tag_id');
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    /**
     * @return HasMany<WorkflowRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }
}
