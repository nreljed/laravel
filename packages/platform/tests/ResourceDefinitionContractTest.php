<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Support\ResourceDefinition;
use PHPUnit\Framework\TestCase;

final class ResourceDefinitionContractTest extends TestCase
{
    /**
     * The type list an unknown field type is reported against, derived from the
     * catalog rather than pasted. Hardcoding it made every new field type a
     * test edit - and when one was missed the suite failed on an unrelated
     * message, hiding whether the real check still worked.
     */
    private static function allowedFieldTypes(): string
    {
        return 'must be one of ['.implode('|', ResourceDefinition::FIELD_TYPES).']';
    }

    public function test_defaults_are_empty(): void
    {
        $definition = new ResourceDefinition(key: 'products', label: 'Products');

        $this->assertSame('products', $definition->getKey());
        $this->assertSame('Products', $definition->getLabel());
        $this->assertNull($definition->getSingular());
        $this->assertSame([], $definition->getTable());
        $this->assertSame([], $definition->getForm());
        $this->assertSame([], $definition->getRelations());
        $this->assertSame([], $definition->getRoutes());
        $this->assertSame([], $definition->getPermissions());
    }

    public function test_to_array_round_trips_through_from_array(): void
    {
        $definition = new ResourceDefinition(
            key: 'products',
            label: 'Products',
            singular: 'Product',
            table: [
                'columns' => [
                    ['name' => 'name', 'header' => 'Name'],
                ],
            ],
            form: [
                ['title' => 'Details', 'fields' => [['name' => 'name', 'type' => 'text']]],
            ],
            relations: [
                ['key' => 'variations', 'title' => 'Variations'],
            ],
            routes: [
                'index' => 'dashboard.products.index',
                'edit' => 'dashboard.products.edit',
            ],
            permissions: [
                'view' => 'products.view',
                'create' => 'products.create',
            ],
        );

        $this->assertSame(
            $definition->toArray(),
            ResourceDefinition::fromArray($definition->toArray())->toArray(),
            'fromArray() must reproduce a descriptor exactly: getAdminResources() caches these for the memoised registry.',
        );
    }

    public function test_to_array_omits_nothing_and_adds_nothing(): void
    {
        $definition = new ResourceDefinition(
            key: 'products',
            label: 'Products',
            singular: 'Product',
            table: ['columns' => [['name' => 'id', 'header' => 'ID']]],
        );

        $this->assertSame(
            ['key', 'label', 'singular', 'table', 'form', 'relations', 'routes', 'permissions', 'recordParam'],
            array_keys($definition->toArray()),
        );
    }

    public function test_from_array_falls_back_to_empty_values(): void
    {
        $definition = ResourceDefinition::fromArray([]);

        $this->assertSame('', $definition->getKey());
        $this->assertSame('', $definition->getLabel());
        $this->assertNull($definition->getSingular());
        $this->assertSame([], $definition->getRoutes());
        $this->assertSame([], $definition->getPermissions());
        $this->assertSame(ResourceDefinition::DEFAULT_RECORD_PARAM, $definition->getRecordParam());
    }

    public function test_record_param_defaults_to_record_and_survives_a_round_trip(): void
    {
        $this->assertSame('record', (new ResourceDefinition(key: 'a', label: 'A'))->getRecordParam());

        $custom = ResourceDefinition::fromArray([
            'key' => 'shop.products',
            'label' => 'Products',
            'recordParam' => 'product',
        ]);

        $this->assertSame('product', $custom->getRecordParam());
        $this->assertSame('product', $custom->toArray()['recordParam']);
    }

    public function test_a_blank_record_param_falls_back_rather_than_binding_nothing(): void
    {
        $this->assertSame(
            ResourceDefinition::DEFAULT_RECORD_PARAM,
            ResourceDefinition::fromArray(['key' => 'a', 'label' => 'A', 'recordParam' => ''])->getRecordParam(),
        );
    }

    public function test_record_param_must_be_an_identifier(): void
    {
        $violations = (new ResourceDefinition(key: 'a', label: 'A', recordParam: '9lives'))
            ->violations();

        $this->assertContains(
            'recordParam must be a valid route parameter name (identifier), got \'9lives\'',
            $violations,
        );
    }

    public function test_header_actions_with_a_file_input_and_a_get_link_pass_validation(): void
    {
        $definition = new ResourceDefinition(
            key: 'shop.products',
            label: 'Products',
            table: [
                'columns' => [['name' => 'id', 'header' => 'ID']],
                'headerActions' => [
                    ['label' => 'Export CSV', 'routeName' => 'dashboard.shop.products.export', 'method' => 'get'],
                    ['label' => 'Import', 'routeName' => 'dashboard.shop.products.import', 'method' => 'post', 'fileInput' => true, 'accept' => '.csv'],
                ],
            ],
        );

        $this->assertSame([], $definition->violations());
    }

    public function test_bulk_actions_with_fields_and_an_endpoint_pass_validation(): void
    {
        $definition = new ResourceDefinition(
            key: 'shop.products',
            label: 'Products',
            table: [
                'columns' => [['name' => 'id', 'header' => 'ID']],
                'bulkEndpoint' => 'dashboard.shop.products.bulk',
                'bulkActions' => [
                    ['label' => 'Delete', 'value' => 'delete', 'destructive' => true, 'confirmTitle' => 'Delete products?'],
                    [
                        'label' => 'Add Categories',
                        'value' => 'add_category',
                        'fields' => [
                            ['name' => 'category_ids', 'type' => 'relation', 'optionsUrl' => 'dashboard.shop.categories.options'],
                        ],
                    ],
                ],
            ],
        );

        $this->assertSame([], $definition->violations());
    }

    public function test_bulk_actions_without_an_endpoint_are_rejected(): void
    {
        $definition = new ResourceDefinition(
            key: 'shop.products',
            label: 'Products',
            table: [
                'columns' => [['name' => 'id', 'header' => 'ID']],
                'bulkActions' => [['label' => 'Delete', 'value' => 'delete']],
            ],
        );

        $this->assertContains(
            'table.bulkActions requires table.bulkEndpoint (a route name) to POST the action to',
            $definition->violations(),
        );
    }

    public function test_bulk_action_shape_is_validated(): void
    {
        $definition = new ResourceDefinition(
            key: 'shop.products',
            label: 'Products',
            table: [
                'columns' => [['name' => 'id', 'header' => 'ID']],
                'bulkEndpoint' => 'dashboard.shop.products.bulk',
                'bulkActions' => [
                    ['label' => 'No value', 'destructive' => 'yes'],
                    ['label' => 'Bad key', 'value' => 'x', 'nope' => 1],
                    ['label' => 'Bad fields', 'value' => 'x', 'fields' => 'not-an-array'],
                ],
            ],
        );

        $joined = implode("\n", $definition->violations());

        $this->assertStringContainsString('table.bulkActions[0].value must be a non-empty string', $joined);
        $this->assertStringContainsString('table.bulkActions[0].destructive must be a boolean', $joined);
        $this->assertStringContainsString('table.bulkActions[1].nope is not expressible', $joined);
        $this->assertStringContainsString('table.bulkActions[2].fields must be an array of field definitions', $joined);
    }

    public function test_header_action_shape_is_validated(): void
    {
        $definition = new ResourceDefinition(
            key: 'shop.products',
            label: 'Products',
            table: [
                'columns' => [['name' => 'id', 'header' => 'ID']],
                'headerActions' => [
                    ['routeName' => 'dashboard.shop.products.export'],
                    ['label' => 'Bad method', 'routeName' => 'x', 'method' => 'put'],
                    ['label' => 'Bad fileInput', 'routeName' => 'x', 'fileInput' => 'yes'],
                ],
            ],
        );

        $joined = implode("\n", $definition->violations());

        $this->assertStringContainsString('table.headerActions[0].label must be a non-empty string', $joined);
        $this->assertStringContainsString('table.headerActions[1].method must be one of [get|post]', $joined);
        $this->assertStringContainsString('table.headerActions[2].fileInput must be a boolean', $joined);
    }

    public function test_the_generator_output_is_schema_valid(): void
    {
        $definition = new ResourceDefinition(
            key: 'products',
            label: 'Products',
            singular: 'Product',
            table: [
                'columns' => [
                    ['name' => 'id', 'header' => 'ID'],
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                ],
                'searchKey' => 'name',
                'filters' => [
                    ['key' => 'status', 'options' => [['label' => 'Active', 'value' => 'active']]],
                ],
            ],
            form: [
                ['title' => 'Details', 'fields' => [
                    ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                    ['name' => 'price', 'type' => 'money', 'colSpan' => 2],
                ]],
            ],
            relations: [
                ['key' => 'variations', 'title' => 'Variations'],
            ],
            routes: [
                'index' => 'dashboard.products.index',
                'create' => 'dashboard.products.create',
                'store' => 'dashboard.products.store',
                'edit' => 'dashboard.products.edit',
                'update' => 'dashboard.products.update',
                'destroy' => 'dashboard.products.destroy',
            ],
        );

        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop-module');
    }

    public function test_key_and_label_are_mandatory(): void
    {
        $definition = new ResourceDefinition(key: '', label: 'Products');

        $this->assertContains('key must be a non-empty string', $definition->violations());

        $definition = new ResourceDefinition(key: 'products', label: '');

        $this->assertContains('label must be a non-empty string', $definition->violations());
    }

    public function test_ts_closure_surfaces_are_rejected(): void
    {
        $definition = new ResourceDefinition(
            key: 'products',
            label: 'Products',
            form: [
                ['fields' => [['name' => 'sku', 'type' => 'text', 'serialize' => fn () => null]]],
            ],
            table: [
                // bulkActions is serializable now, but a `show` predicate is a
                // TS closure and stays off the PHP surface.
                'bulkActions' => [['label' => 'Delete', 'value' => 'delete', 'show' => fn () => true]],
                'columns' => [['name' => 'id', 'header' => 'ID', 'cell' => fn () => 'x']],
            ],
            relations: [
                ['key' => 'variations', 'component' => 'VariationsManager'],
            ],
        );

        $violations = $definition->violations();

        $joined = implode("\n", $violations);

        $this->assertStringContainsString('table.bulkActions[0].show is not expressible in a PHP descriptor', $joined);
        $this->assertStringContainsString('table.columns[0].cell is not expressible in a PHP descriptor', $joined);
        $this->assertStringContainsString('form.0.fields[0].serialize is not expressible in a PHP descriptor', $joined);
        $this->assertStringContainsString('relations[0].component is not expressible in a PHP descriptor', $joined);
    }

    public function test_unknown_field_type_is_rejected(): void
    {
        $definition = new ResourceDefinition(
            key: 'products',
            label: 'Products',
            form: [
                ['fields' => [['name' => 'swatch', 'type' => 'colour-swatch']]],
            ],
        );

        $this->assertStringContainsString(
            'form.0.fields[0].type '.self::allowedFieldTypes()." (got 'colour-swatch')",
            implode("\n", $definition->violations()),
        );
    }

    public function test_dynamic_has_no_built_in_control_and_is_rejected_for_php_descriptors(): void
    {
        $definition = new ResourceDefinition(
            key: 'products',
            label: 'Products',
            form: [
                ['fields' => [['name' => 'tail', 'type' => 'dynamic']]],
            ],
        );

        $this->assertStringContainsString(
            'form.0.fields[0].type '.self::allowedFieldTypes()." (got 'dynamic')",
            implode("\n", $definition->violations()),
        );
    }

    public function test_column_without_name_and_route_that_is_not_a_route_name_are_rejected(): void
    {
        $definition = new ResourceDefinition(
            key: 'products',
            label: 'Products',
            table: ['columns' => [['header' => 'ID']]],
            routes: ['edit' => 42, 'rename' => 'dashboard.products.rename'],
        );

        $violations = $definition->violations();

        $this->assertContains('table.columns[0].name must be a non-empty accessor key', $violations);
        $this->assertContains('routes.edit must be a non-empty route name (string), got int', $violations);
        $this->assertContains('routes declares unknown slot [rename]', $violations);
    }

    public function test_permission_slots_reject_unknown_slots_and_empty_slugs(): void
    {
        $definition = new ResourceDefinition(
            key: 'products',
            label: 'Products',
            permissions: ['read' => 'products.view', 'view' => ''],
        );

        $violations = $definition->violations();

        $this->assertContains('permissions declares unknown slot [read]', $violations);
        $this->assertContains('permissions.view must be a non-empty permission slug (string), got an empty string', $violations);

        $this->assertSame(['view', 'create', 'edit', 'delete'], ResourceDefinition::PERMISSION_KEYS);
    }

    public function test_valid_permissions_pass_validation(): void
    {
        $definition = new ResourceDefinition(
            key: 'products',
            label: 'Products',
            permissions: [
                'view' => 'products.view',
                'create' => 'products.create',
                'edit' => 'products.edit',
                'delete' => 'products.delete',
            ],
        );

        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop-module');
    }

    public function test_assert_valid_attributes_the_owner_slug(): void
    {
        $definition = new ResourceDefinition(key: 'products', label: 'Products', relations: [['title' => 'Variations']]);

        try {
            $definition->assertValid('shop-module');
            $this->fail('assertValid() must throw for a malformed descriptor.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString(
                'Feature [shop-module] declared resource [products]',
                $exception->getMessage(),
            );
            $this->assertStringContainsString('relations[0].key must be a non-empty string', $exception->getMessage());
        }
    }

    public function test_relation_field_with_static_options_is_accepted(): void
    {
        $definition = new ResourceDefinition(
            key: 'webhooks',
            label: 'Webhooks',
            form: [
                ['title' => 'Details', 'fields' => [
                    ['name' => 'events', 'type' => 'relation', 'label' => 'Events', 'required' => true, 'options' => [
                        ['label' => 'Order created', 'value' => 'order.created'],
                        ['label' => 'Order paid', 'value' => 'order.paid'],
                    ]],
                ]],
            ],
        );

        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop-module');
    }

    public function test_relation_field_with_lazy_options_url_is_accepted(): void
    {
        $definition = new ResourceDefinition(
            key: 'coupons',
            label: 'Coupons',
            form: [
                ['title' => 'Discount', 'fields' => [
                    ['name' => 'product_ids', 'type' => 'relation', 'optionsUrl' => 'dashboard.shop.products.options'],
                ]],
            ],
        );

        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop-module');
    }

    public function test_relation_field_without_an_options_source_is_rejected(): void
    {
        $definition = new ResourceDefinition(
            key: 'coupons',
            label: 'Coupons',
            form: [
                ['title' => 'Discount', 'fields' => [['name' => 'product_ids', 'type' => 'relation']]],
            ],
        );

        $this->assertStringContainsString(
            'form.0.fields[0] is a relation field and must declare an options source: options or optionsUrl',
            implode("\n", $definition->violations()),
        );
    }

    public function test_options_url_must_be_a_non_empty_route_name(): void
    {
        $definition = new ResourceDefinition(
            key: 'coupons',
            label: 'Coupons',
            form: [
                ['title' => 'Discount', 'fields' => [
                    ['name' => 'product_ids', 'type' => 'relation', 'optionsUrl' => ''],
                ]],
            ],
        );

        $violations = $definition->violations();

        $this->assertContains('form.0.fields[0].optionsUrl must be a non-empty route name (string)', $violations);
    }

    public function test_valid_row_actions_pass_validation(): void
    {
        $definition = new ResourceDefinition(
            key: 'reviews',
            label: 'Reviews',
            table: [
                'columns' => [['name' => 'id', 'header' => 'ID']],
                'rowActions' => [
                    ['label' => 'Approve', 'routeName' => 'dashboard.shop.reviews.approve', 'method' => 'post', 'confirm' => 'Approve this review?'],
                    ['label' => 'Set status', 'routeName' => 'dashboard.shop.reviews.status', 'method' => 'patch'],
                    ['label' => 'Delete', 'routeName' => 'dashboard.shop.reviews.destroy', 'method' => 'delete', 'destructive' => true],
                    ['label' => 'Show', 'routeName' => 'dashboard.shop.reviews.show', 'method' => 'get'],
                    ['label' => 'Rename', 'routeName' => 'dashboard.shop.reviews.update', 'method' => 'put'],
                ],
            ],
        );

        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop-module');
    }

    public function test_row_action_shape_is_validate_d(): void
    {
        $definition = new ResourceDefinition(
            key: 'reviews',
            label: 'Reviews',
            table: [
                'columns' => [['name' => 'id', 'header' => 'ID']],
                'rowActions' => [
                    ['routeName' => 'dashboard.shop.reviews.approve', 'method' => 'trace'],
                    ['label' => 'Purge', 'method' => 'post'],
                    ['label' => 'Logo', 'routeName' => 'dashboard.shop.reviews.logo', 'confirm' => 3],
                    ['label' => 'Broken', 'routeName' => 'dashboard.shop.reviews.broken', 'destructive' => 'yes', 'icon' => 'swatch'],
                ],
            ],
        );

        $joined = implode("\n", $definition->violations());

        $this->assertStringContainsString('table.rowActions[0].label must be a non-empty string', $joined);
        $this->assertStringContainsString('table.rowActions[0].method must be one of [get|post|put|patch|delete]', $joined);
        $this->assertStringContainsString('table.rowActions[1].routeName must be a non-empty route name', $joined);
        $this->assertStringContainsString('table.rowActions[2].confirm must be a non-empty string when present', $joined);
        $this->assertStringContainsString('table.rowActions[3].destructive must be a boolean', $joined);
        $this->assertStringContainsString('table.rowActions[3].icon is not expressible in a PHP descriptor row action', $joined);
    }

    /**
     * A row action that carries a `dialogTitle` and a `checkboxList` field is
     * the exact shape the products index declares, and it has to survive
     * validation: rejecting it would block the migration at boot.
     */
    public function test_bulk_action_with_a_dialog_title_and_a_checkbox_list_field_passes_validation(): void
    {
        $definition = new ResourceDefinition(
            key: 'shop.products',
            label: 'Products',
            table: [
                'columns' => [['name' => 'id', 'header' => 'ID']],
                'bulkEndpoint' => 'dashboard.shop.products.bulk',
                'bulkActions' => [
                    [
                        'label' => 'Add Categories...',
                        'dialogTitle' => 'Choose categories to add',
                        'value' => 'add_category',
                        'fields' => [
                            [
                                'name' => 'category_ids',
                                'type' => 'checkboxList',
                                'label' => 'Categories',
                                'required' => true,
                                'options' => [['label' => 'Apparel', 'value' => 1]],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $this->assertSame([], $definition->violations());
    }

    /**
     * A mistyped field type inside a bulk action would otherwise only surface
     * in a dialog at runtime; the descriptor is rejected at boot instead.
     */
    public function test_a_bulk_action_field_with_an_unknown_type_is_rejected(): void
    {
        $definition = new ResourceDefinition(
            key: 'shop.products',
            label: 'Products',
            table: [
                'columns' => [['name' => 'id', 'header' => 'ID']],
                'bulkEndpoint' => 'dashboard.shop.products.bulk',
                'bulkActions' => [
                    [
                        'label' => 'Add Categories',
                        'value' => 'add_category',
                        'fields' => [['name' => 'category_ids', 'type' => 'checkboxes', 'label' => 'Categories']],
                    ],
                ],
            ],
        );

        $joined = implode("\n", $definition->violations());

        $this->assertStringContainsString('table.bulkActions[0].fields[0].type', $joined);
        $this->assertStringContainsString('checkboxes', $joined);
    }

    public function test_row_actions_with_no_columns_are_rejected(): void
    {
        $definition = new ResourceDefinition(
            key: 'reviews',
            label: 'Reviews',
            table: ['rowActions' => [['label' => 'Approve', 'routeName' => 'dashboard.shop.reviews.approve']]],
        );

        $this->assertContains('table.columns must be an array', $definition->violations());
    }

    public function test_search_keys_are_accepted(): void
    {
        $definition = new ResourceDefinition(
            key: 'orders',
            label: 'Orders',
            table: [
                'columns' => [['name' => 'order_number', 'header' => 'Number']],
                'searchKeys' => ['order_number', 'customer.email'],
            ],
        );

        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop-module');
    }

    public function test_search_keys_shape_is_validated(): void
    {
        $definition = new ResourceDefinition(
            key: 'orders',
            label: 'Orders',
            table: [
                'columns' => [['name' => 'id', 'header' => 'ID']],
                'searchKeys' => ['order_number', '', 42],
            ],
        );

        $joined = implode("\n", $definition->violations());

        $this->assertStringContainsString('table.searchKeys[1] must be a non-empty string', $joined);
        $this->assertStringContainsString('table.searchKeys[2] must be a non-empty string', $joined);

        $definition = new ResourceDefinition(
            key: 'orders',
            label: 'Orders',
            table: [
                'columns' => [['name' => 'id', 'header' => 'ID']],
                'searchKeys' => 'name',
            ],
        );

        $this->assertStringContainsString(
            'table.searchKeys must be an array of column accessor keys',
            implode("\n", $definition->violations()),
        );
    }

    public function test_media_field_endpoints_and_multiple_flag_are_accepted(): void
    {
        $definition = new ResourceDefinition(
            key: 'products',
            label: 'Products',
            form: [
                ['title' => 'Media', 'fields' => [
                    [
                        'name' => 'featured_image',
                        'type' => 'media',
                        'uploadUrl' => 'dashboard.shop.products.upload-image',
                        'deleteUrl' => 'dashboard.shop.products.destroy-image',
                        'subdirectory' => 'products',
                    ],
                    ['name' => 'images', 'type' => 'media', 'multiple' => true],
                ]],
            ],
        );

        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop-module');
    }

    public function test_media_field_endpoint_shape_is_validated(): void
    {
        $definition = new ResourceDefinition(
            key: 'products',
            label: 'Products',
            form: [
                ['title' => 'Media', 'fields' => [
                    [
                        'name' => 'featured_image',
                        'type' => 'media',
                        'uploadUrl' => '',
                        'deleteUrl' => ['x'],
                        'subdirectory' => 7,
                        'multiple' => 'yes',
                        'unexpected' => 'nope',
                    ],
                ]],
            ],
        );

        $joined = implode("\n", $definition->violations());

        $this->assertStringContainsString('form.0.fields[0].uploadUrl must be a non-empty route name (string)', $joined);
        $this->assertStringContainsString('form.0.fields[0].deleteUrl must be a non-empty route name (string)', $joined);
        $this->assertStringContainsString('form.0.fields[0].subdirectory must be a non-empty string', $joined);
        $this->assertStringContainsString('form.0.fields[0].multiple must be a boolean', $joined);
        $this->assertStringContainsString('form.0.fields[0].unexpected is not expressible in a PHP descriptor', $joined);
    }

    public function test_money_field_with_static_currency_options_passes(): void
    {
        $definition = new ResourceDefinition(
            key: 'products',
            label: 'Products',
            form: [
                ['title' => 'Pricing', 'fields' => [
                    [
                        'name' => 'unit_price',
                        'type' => 'money',
                        'options' => [
                            ['label' => 'EUR', 'value' => 'EUR'],
                            ['label' => 'USD', 'value' => 'USD'],
                        ],
                    ],
                ]],
            ],
        );

        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop-module');
    }

    public function test_nested_field_with_valid_rows_is_accepted(): void
    {
        $definition = new ResourceDefinition(
            key: 'zones',
            label: 'Zones',
            form: [
                ['title' => 'Methods', 'fields' => [
                    [
                        'name' => 'methods',
                        'type' => 'nested',
                        'rowTitleField' => 'method_id',
                        'rows' => [
                            ['name' => 'method_id', 'type' => 'relation', 'options' => [
                                ['label' => 'Flat rate', 'value' => 'flat'],
                            ]],
                            ['name' => 'override_price', 'type' => 'money'],
                            ['name' => 'enabled', 'type' => 'boolean'],
                        ],
                    ],
                ]],
            ],
        );

        $this->assertSame([], $definition->violations());
        $definition->assertValid('shop-module');
    }

    public function test_nested_field_without_rows_is_rejected(): void
    {
        $definition = new ResourceDefinition(
            key: 'zones',
            label: 'Zones',
            form: [
                ['title' => 'Methods', 'fields' => [['name' => 'methods', 'type' => 'nested']]],
            ],
        );

        $this->assertContains(
            'form.0.fields[0] is a nested field and must declare a non-empty rows array',
            $definition->violations(),
        );
    }

    public function test_nested_row_sub_fields_are_validated_recursively(): void
    {
        $definition = new ResourceDefinition(
            key: 'zones',
            label: 'Zones',
            form: [
                ['title' => 'Methods', 'fields' => [
                    [
                        'name' => 'methods',
                        'type' => 'nested',
                        'rows' => [
                            ['name' => 'method_id', 'type' => 'relation'],
                            ['name' => 'override_price', 'type' => 'not-a-type'],
                            ['name' => 'enabled', 'type' => 'boolean', 'render' => fn () => 'nope'],
                        ],
                    ],
                ]],
            ],
        );

        $joined = implode("\n", $definition->violations());

        $this->assertStringContainsString(
            'form.0.fields[0].rows[0] is a relation field and must declare an options source',
            $joined,
        );
        $this->assertStringContainsString(
            'form.0.fields[0].rows[1].type '.self::allowedFieldTypes()." (got 'not-a-type')",
            $joined,
        );
        $this->assertStringContainsString(
            'form.0.fields[0].rows[2].render is not expressible in a PHP descriptor',
            $joined,
        );
    }

    public function test_nested_row_title_field_must_name_a_sub_field(): void
    {
        $definition = new ResourceDefinition(
            key: 'zones',
            label: 'Zones',
            form: [
                ['title' => 'Methods', 'fields' => [
                    [
                        'name' => 'methods',
                        'type' => 'nested',
                        'rowTitleField' => 'name',
                        'rows' => [
                            ['name' => 'method_id', 'type' => 'relation', 'options' => [['label' => 'Flat', 'value' => 'flat']]],
                        ],
                    ],
                ]],
            ],
        );

        $joined = implode("\n", $definition->violations());

        $this->assertStringContainsString(
            'form.0.fields[0].rowTitleField [name] names no sub-field of rows (got method_id)',
            $joined,
        );
    }
}
