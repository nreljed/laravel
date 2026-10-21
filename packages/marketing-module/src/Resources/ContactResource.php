<?php

namespace Nreljed\MarketingModule\Resources;

use Nreljed\MarketingModule\Enums\ContactStatus;
use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for the audience.
 *
 * The contact form is intentionally thin — name, email, status, source. A
 * custom field builder, notes and ownership are a CRM, and this module is
 * an audience for automations rather than a CRM.
 */
final class ContactResource
{
    public const KEY = 'marketing.contacts';

    public const ROUTES = [
        'index' => 'dashboard.marketing.contacts.index',
        'create' => 'dashboard.marketing.contacts.create',
        'store' => 'dashboard.marketing.contacts.store',
        'edit' => 'dashboard.marketing.contacts.edit',
        'update' => 'dashboard.marketing.contacts.update',
        'destroy' => 'dashboard.marketing.contacts.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Contacts',
            singular: 'Contact',
            table: [
                'columns' => [
                    ['name' => 'email', 'header' => 'Email', 'sortable' => true],
                    ['name' => 'first_name', 'header' => 'First Name'],
                    ['name' => 'last_name', 'header' => 'Last Name'],
                    ['name' => 'status', 'header' => 'Status', 'sortable' => true],
                    ['name' => 'source', 'header' => 'Source'],
                    ['name' => 'created_at', 'header' => 'Created'],
                ],
                'searchKeys' => ['email', 'first_name', 'last_name'],
                'searchPlaceholder' => 'Search contacts',
            ],
            form: [
                [
                    'title' => 'Details',
                    'fields' => [
                        ['name' => 'email', 'type' => 'text', 'label' => 'Email'],
                        ['name' => 'first_name', 'type' => 'text', 'label' => 'First Name'],
                        ['name' => 'last_name', 'type' => 'text', 'label' => 'Last Name'],
                        ['name' => 'phone', 'type' => 'text', 'label' => 'Phone'],
                        [
                            'name' => 'status',
                            'type' => 'select',
                            'label' => 'Status',
                            'options' => ContactStatus::options(),
                        ],
                        ['name' => 'source', 'type' => 'text', 'label' => 'Source', 'help' => 'Where the contact came from.'],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'marketing.contacts.manage',
                'create' => 'marketing.contacts.manage',
                'edit' => 'marketing.contacts.manage',
                'delete' => 'marketing.contacts.manage',
            ],
        );
    }
}
