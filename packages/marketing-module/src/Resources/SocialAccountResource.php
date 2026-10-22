<?php

namespace Nreljed\MarketingModule\Resources;

use Nreljed\MarketingModule\Enums\SocialAccountStatus;
use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for connected social accounts.
 *
 * There is no create/store slot: an account comes into existence by
 * connecting a platform, which is an OAuth round trip and not a form
 * submission. The form covers the fields a user owns afterwards — the
 * display name, the handle, and the board a Pinterest account posts to.
 */
final class SocialAccountResource
{
    public const KEY = 'marketing.accounts';

    public const ROUTES = [
        'index' => 'dashboard.marketing.accounts.index',
        'edit' => 'dashboard.marketing.accounts.edit',
        'update' => 'dashboard.marketing.accounts.update',
        'destroy' => 'dashboard.marketing.accounts.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Social Accounts',
            singular: 'Social Account',
            table: [
                'columns' => [
                    ['name' => 'account_name', 'header' => 'Account', 'sortable' => true],
                    ['name' => 'platform', 'header' => 'Platform', 'sortable' => true],
                    ['name' => 'username', 'header' => 'Handle'],
                    ['name' => 'status', 'header' => 'Status'],
                    ['name' => 'last_validated_at', 'header' => 'Last Checked'],
                ],
                'searchKeys' => ['account_name', 'username'],
                'searchPlaceholder' => 'Search accounts',
                'headerActions' => [
                    [
                        'label' => 'Connect account',
                        // Accounts are created by an OAuth handshake that needs a
                        // platform, and a header action has no parameter to bind.
                        // The dashboard owns the per-platform connect buttons.
                        'routeName' => 'dashboard.marketing.dashboard',
                        'method' => 'get',
                    ],
                ],
            ],
            form: [
                [
                    'title' => 'Details',
                    'fields' => [
                        ['name' => 'account_name', 'type' => 'text', 'label' => 'Account Name', 'required' => true],
                        ['name' => 'username', 'type' => 'text', 'label' => 'Handle'],
                        ['name' => 'board_id', 'type' => 'text', 'label' => 'Board ID', 'help' => 'Pinterest only. The board new pins are published to.'],
                        [
                            'name' => 'status',
                            'type' => 'select',
                            'label' => 'Status',
                            'options' => SocialAccountStatus::options(),
                        ],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'marketing.accounts.manage',
                'edit' => 'marketing.accounts.manage',
                'delete' => 'marketing.accounts.manage',
            ],
        );
    }
}
