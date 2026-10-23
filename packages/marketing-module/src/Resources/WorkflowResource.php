<?php

namespace Nreljed\MarketingModule\Resources;

use Nreljed\MarketingModule\Enums\ContactStatus;
use Nreljed\MarketingModule\Enums\WorkflowAction;
use Nreljed\MarketingModule\Enums\WorkflowTrigger;
use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for audience and publishing automations.
 *
 * One rule is one trigger and one action. The form shows every action's
 * fields because the platform's declarative schema cannot hide fields based on
 * another value; the request validates the combination instead.
 */
final class WorkflowResource
{
    public const KEY = 'marketing.workflows';

    public const ROUTES = [
        'index' => 'dashboard.marketing.workflows.index',
        'create' => 'dashboard.marketing.workflows.create',
        'store' => 'dashboard.marketing.workflows.store',
        'edit' => 'dashboard.marketing.workflows.edit',
        'update' => 'dashboard.marketing.workflows.update',
        'destroy' => 'dashboard.marketing.workflows.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Workflows',
            singular: 'Workflow',
            table: [
                'columns' => [
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'trigger_label', 'header' => 'Trigger'],
                    ['name' => 'action_label', 'header' => 'Action'],
                    ['name' => 'is_active', 'header' => 'Active'],
                    ['name' => 'run_count', 'header' => 'Runs'],
                    ['name' => 'last_run_at', 'header' => 'Last run'],
                ],
                'searchKeys' => ['name', 'description'],
                'searchPlaceholder' => 'Search workflows',
            ],
            form: [
                [
                    'title' => 'Rule',
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                        [
                            'name' => 'description',
                            'type' => 'textarea',
                            'label' => 'Description',
                            'rows' => 2,
                        ],
                        [
                            'name' => 'trigger',
                            'type' => 'select',
                            'label' => 'Trigger',
                            'options' => WorkflowTrigger::options(),
                            'defaultValue' => WorkflowTrigger::ContactCreated->value,
                            'required' => true,
                        ],
                        [
                            'name' => 'action',
                            'type' => 'select',
                            'label' => 'Action',
                            'options' => WorkflowAction::options(),
                            'defaultValue' => WorkflowAction::AddTag->value,
                            'required' => true,
                            'help' => 'Post published rules can only send a webhook.',
                        ],
                        [
                            'name' => 'condition_tag_id',
                            'type' => 'select',
                            'label' => 'Only when tagged',
                            'optionsUrl' => 'dashboard.marketing.workflows.tag-options',
                            'help' => 'Optional. Contact events only. Runs only when the contact already has this tag.',
                        ],
                        [
                            'name' => 'is_active',
                            'type' => 'boolean',
                            'label' => 'Active',
                            'defaultValue' => true,
                        ],
                    ],
                ],
                [
                    'title' => 'Action details',
                    'fields' => [
                        [
                            'name' => 'tag_id',
                            'type' => 'select',
                            'label' => 'Tag',
                            'optionsUrl' => 'dashboard.marketing.workflows.tag-options',
                            'help' => 'Required when adding or removing a tag.',
                        ],
                        [
                            'name' => 'contact_status',
                            'type' => 'select',
                            'label' => 'Contact status',
                            'options' => ContactStatus::options(),
                            'defaultValue' => ContactStatus::Lead->value,
                            'help' => 'Required when setting a contact status.',
                        ],
                        [
                            'name' => 'webhook_url',
                            'type' => 'text',
                            'label' => 'Webhook URL',
                            'help' => 'Required when sending a webhook. Receives the event payload as JSON.',
                        ],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'marketing.workflows.manage',
                'create' => 'marketing.workflows.manage',
                'edit' => 'marketing.workflows.manage',
                'delete' => 'marketing.workflows.manage',
            ],
        );
    }
}
