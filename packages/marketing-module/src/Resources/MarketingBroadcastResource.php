<?php

namespace Nreljed\MarketingModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

final class MarketingBroadcastResource
{
    public const KEY = 'marketing.broadcasts';

    public const ROUTES = [
        'index' => 'dashboard.marketing.broadcasts.index',
        'create' => 'dashboard.marketing.broadcasts.create',
        'store' => 'dashboard.marketing.broadcasts.store',
        'edit' => 'dashboard.marketing.broadcasts.edit',
        'update' => 'dashboard.marketing.broadcasts.update',
        'destroy' => 'dashboard.marketing.broadcasts.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Broadcasts',
            singular: 'Broadcast',
            table: [
                'columns' => [
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'subject', 'header' => 'Subject'],
                    ['name' => 'status_label', 'header' => 'Status', 'sortable' => true],
                    ['name' => 'recipient_count', 'header' => 'Recipients'],
                    ['name' => 'delivered_count', 'header' => 'Delivered'],
                    ['name' => 'scheduled_at', 'header' => 'Scheduled'],
                ],
                'searchKeys' => ['name', 'subject'],
                'searchPlaceholder' => 'Search broadcasts',
            ],
            form: [
                [
                    'title' => 'Message',
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                        ['name' => 'channel', 'type' => 'select', 'label' => 'Channel', 'options' => [
                            ['label' => 'Email', 'value' => 'email'],
                            ['label' => 'SMS', 'value' => 'sms'],
                            ['label' => 'WhatsApp', 'value' => 'whatsapp'],
                        ], 'required' => true],
                        ['name' => 'subject', 'type' => 'text', 'label' => 'Subject', 'help' => 'Used for email broadcasts.'],
                        ['name' => 'body_html', 'type' => 'textarea', 'label' => 'Body (HTML)', 'rows' => 10, 'help' => 'Used for email broadcasts.'],
                        ['name' => 'body_text', 'type' => 'textarea', 'label' => 'Body (text)', 'rows' => 6, 'help' => 'Used for SMS and WhatsApp.'],
                        ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => [
                            ['label' => 'Draft', 'value' => 'draft'],
                            ['label' => 'Scheduled', 'value' => 'scheduled'],
                            ['label' => 'Cancelled', 'value' => 'cancelled'],
                        ], 'required' => true],
                    ],
                ],
                [
                    'title' => 'Audience',
                    'fields' => [
                        ['name' => 'segment_id', 'type' => 'text', 'label' => 'Segment ID'],
                        ['name' => 'tag_id', 'type' => 'text', 'label' => 'Tag ID'],
                        ['name' => 'scheduled_at', 'type' => 'datetime', 'label' => 'Scheduled at'],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'marketing.broadcasts.manage',
                'create' => 'marketing.broadcasts.manage',
                'edit' => 'marketing.broadcasts.manage',
                'delete' => 'marketing.broadcasts.manage',
            ],
        );
    }
}
