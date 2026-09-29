<?php

namespace Nreljed\ShopModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\Events\WebhookEvents;

/**
 * Declarative admin resource for webhooks (plan 6.1 wave-6 conversion on the
 * kernel resource path from platform 0.1.67).
 *
 * `events` is the first use of the 0.1.67 serialized `relation` field: the
 * value is the raw scalar array held in the row's own JSON `events` column,
 * and the option catalog is static (WebhookEvents::all()), so it ships as
 * declared `options` rather than a lazy `optionsUrl`. The table column of the
 * same name is display-only — the index controller hands a comma-joined
 * string so the Array-value column renders readable text, while the edit form
 * still receives the real array from the record.
 *
 * The toggle-active / test / calls row actions are serialized `routeName`
 * entries the kernel's bridge binds to the literal {@see \Nreljed\Platform\Support\ResourceRenderer::RECORD_PARAM}
 * param, which is why the module routes for those verbs take `{record}`.
 * Mutation endpoints keep their business rules (validation + signature
 * booting) in the module's controller.
 */
final class WebhookResource
{
    public const KEY = 'shop.webhooks';

    public const ROUTES = [
        'index' => 'dashboard.shop.webhooks.index',
        'create' => 'dashboard.shop.webhooks.create',
        'store' => 'dashboard.shop.webhooks.store',
        'edit' => 'dashboard.shop.webhooks.edit',
        'update' => 'dashboard.shop.webhooks.update',
        'destroy' => 'dashboard.shop.webhooks.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        $events = array_map(
            static fn (string $event): array => [
                'value' => $event,
                'label' => ucwords(str_replace(['.', '_'], ' ', $event)),
            ],
            WebhookEvents::all(),
        );

        return new ResourceDefinition(
            key: self::KEY,
            label: 'Webhooks',
            singular: 'Webhook',
            table: [
                'columns' => [
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'url', 'header' => 'URL'],
                    ['name' => 'events', 'header' => 'Events'],
                    ['name' => 'is_active', 'header' => 'Active'],
                    ['name' => 'calls_count', 'header' => 'Calls', 'align' => 'right'],
                    ['name' => 'created_at', 'header' => 'Created'],
                ],
                'searchKey' => 'name',
                'rowActions' => [
                    ['label' => 'Toggle active', 'routeName' => 'dashboard.shop.webhooks.toggle-active', 'method' => 'post'],
                    ['label' => 'Send test', 'routeName' => 'dashboard.shop.webhooks.test', 'method' => 'post', 'confirm' => 'Send a test webhook to this endpoint?'],
                    ['label' => 'View calls', 'routeName' => 'dashboard.shop.webhooks.calls', 'method' => 'get'],
                ],
            ],
            form: [
                [
                    'title' => 'Endpoint',
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'help' => 'A short label so you can spot this endpoint in the list.'],
                        ['name' => 'url', 'type' => 'text', 'label' => 'Payload URL', 'required' => true, 'help' => 'The endpoint that receives event payloads.'],
                    ],
                ],
                [
                    'title' => 'Events',
                    'description' => 'Subscribe to the events you want delivered to this endpoint.',
                    'fields' => [
                        ['name' => 'events', 'type' => 'relation', 'label' => 'Events', 'required' => true, 'colSpan' => 2, 'options' => $events, 'help' => 'At least one event is required; each selected event is delivered as its own payload.'],
                    ],
                ],
                [
                    'title' => 'Delivery',
                    'fields' => [
                        ['name' => 'timeout', 'type' => 'number', 'label' => 'Timeout (s)', 'required' => true, 'defaultValue' => 10, 'help' => '1–60 seconds before the attempt is abandoned.'],
                        ['name' => 'retry_attempts', 'type' => 'number', 'label' => 'Retry Attempts', 'required' => true, 'defaultValue' => 3, 'help' => '0–10 attempts.'],
                        ['name' => 'retry_delay', 'type' => 'number', 'label' => 'Retry Delay (s)', 'required' => true, 'defaultValue' => 10, 'help' => '1–300 seconds between attempts.'],
                    ],
                ],
                [
                    'title' => 'Advanced',
                    'fields' => [
                        ['name' => 'headers', 'type' => 'json', 'label' => 'Custom Headers', 'help' => 'Extra headers as JSON, e.g. {"X-Api-Key": "your-key"}.'],
                        ['name' => 'is_active', 'type' => 'boolean', 'label' => 'Active', 'defaultValue' => true],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'shop.webhooks.manage',
                'create' => 'shop.webhooks.manage',
                'edit' => 'shop.webhooks.manage',
                'delete' => 'shop.webhooks.manage',
            ],
        );
    }
}
