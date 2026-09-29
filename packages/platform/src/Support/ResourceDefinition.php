<?php

namespace Nreljed\Platform\Support;

use InvalidArgumentException;

/**
 * Immutable value object describing one admin CRUD resource a feature exposes.
 *
 * Returned by a feature provider's `getAdminResources()`. The declarative
 * schema and runtime live in the frontend (`resources/js/lib/resource-schema.ts`),
 * and every provider that declares a resource already ships those descriptors
 * across `registerCoreTemplates()` — this object is the server-side half of
 * the same contract. A provider declares a resource with the typed fields
 * below instead of a freeform array, so a misspelled column key fails here
 * rather than surfacing as a column that renders nothing on the admin page.
 *
 * A PHP descriptor can only express the serializable subset of the schema:
 * keys with closures on the TypeScript side (`cell`, `render`,
 * `visibleWhen`, `bulkActions`, `relations.component`) are deliberately
 * rejected, because a descriptor that cannot survive config caching is a
 * descriptor that silently forks the kernel. Anything requiring executable
 * code belongs in the TS-side `defineResource()`.
 */
class ResourceDefinition
{
    /**
     * Field-type union a PHP descriptor may use. Mirrors the `FieldType`
     * union in `resources/js/lib/resource-schema.ts` minus `dynamic`, which
     * only exists to carry a render closure a PHP descriptor cannot express.
     * The `ResourceSchemaContractTest` keeps this list and the shipped kit in
     * lock-step.
     */
    public const FIELD_TYPES = [
        'text',
        'textarea',
        'number',
        'money',
        'select',
        'boolean',
        'date',
        'datetime',
        'slug',
        'json',
        'media',
        'password',
        'hidden',
        'relation',
    ];

    /** Route slots the ResourceIndex/ResourceForm runtime can resolve. */
    public const ROUTE_KEYS = ['index', 'create', 'store', 'edit', 'update', 'destroy'];

    /** Permission slots a descriptor may declare. Anything else is a typo. */
    public const PERMISSION_KEYS = ['view', 'create', 'edit', 'delete'];

    /** `table` keys a PHP descriptor may carry (the serializable ones). */
    private const TABLE_KEYS = [
        'columns',
        'searchKey',
        'searchPlaceholder',
        'filters',
        'defaultPageSize',
        'pageSizeOptions',
        'rowActions',
    ];

    /** `rowAction` keys a PHP descriptor may carry. */
    private const ROW_ACTION_KEYS = ['label', 'routeName', 'method', 'destructive', 'confirm'];

    /** `column` keys a PHP descriptor may carry. `cell` is a closure. */
    private const COLUMN_KEYS = ['name', 'header', 'className', 'sortable', 'hideable', 'align'];

    /** `field` keys a PHP descriptor may carry. `render`/`serialize`/`visibleWhen` are closures. */
    private const FIELD_KEYS = [
        'name',
        'type',
        'label',
        'required',
        'help',
        'placeholder',
        'options',
        'optionsUrl',
        'defaultValue',
        'colSpan',
        'hidden',
    ];

    /** `relation` keys a PHP descriptor may carry. `component` is a closure. */
    private const RELATION_KEYS = ['key', 'title', 'description'];

    public function __construct(
        protected readonly string $key,
        protected readonly string $label,
        protected readonly ?string $singular = null,
        protected readonly array $table = [],
        protected readonly array $form = [],
        protected readonly array $relations = [],
        protected readonly array $routes = [],
        protected readonly array $permissions = [],
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            key: (string) ($config['key'] ?? ''),
            label: (string) ($config['label'] ?? ''),
            singular: isset($config['singular']) ? (string) $config['singular'] : null,
            table: $config['table'] ?? [],
            form: $config['form'] ?? [],
            relations: $config['relations'] ?? [],
            routes: $config['routes'] ?? [],
            permissions: $config['permissions'] ?? [],
        );
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getSingular(): ?string
    {
        return $this->singular;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getTable(): array
    {
        return $this->table;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForm(): array
    {
        return $this->form;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Optional gate slugs for the resource surface. When a slot is declared
     * the owning feature should register the matching route behind it
     * (`permission:` middleware) — the slots here mirror the RBAC catalog so
     * the sidebar can filter and the dashboard checks them.
     *
     * @return array{view?: string, create?: string, edit?: string, delete?: string}
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'singular' => $this->singular,
            'table' => $this->table,
            'form' => $this->form,
            'relations' => $this->relations,
            'routes' => $this->routes,
            'permissions' => $this->permissions,
        ];
    }

    /**
     * Structural violations against the shipped schema
     * (`resources/js/lib/resource-schema.ts`). Empty when the descriptor is
     * fit for the kernel; the FeatureRegistry fails loudly on any violation
     * so a module that drifts off the shared admin surface cannot ship.
     *
     * @return array<int, string>
     */
    public function violations(): array
    {
        $issues = [];

        $this->key === '' && $issues[] = 'key must be a non-empty string';
        $this->label === '' && $issues[] = 'label must be a non-empty string';

        $issues = array_merge($issues, $this->tableViolations());
        $issues = array_merge($issues, $this->formViolations());
        $issues = array_merge($issues, $this->relationViolations());
        $issues = array_merge($issues, $this->permissionViolations());

        foreach ($this->routes as $slot => $route) {
            if (! in_array($slot, self::ROUTE_KEYS, true)) {
                $issues[] = "routes declares unknown slot [{$slot}]";

                continue;
            }

            if (! is_string($route) || $route === '') {
                $issues[] = "routes.{$slot} must be a non-empty route name (string), got "
                    .(is_string($route) ? 'an empty string' : get_debug_type($route));
            }
        }

        return $issues;
    }

    /**
     * Throw unless the descriptor conforms to the shipped schema.
     *
     * Including the owning provider's slug turns a "resource [shop.products]
     * has 3 violations" failure into "feature [shop] declared resource
     * [shop.products] with 3 violations", which is the attribution a module
     * contract test needs.
     *
     * @throws InvalidArgumentException
     */
    public function assertValid(?string $providerSlug = null): void
    {
        $issues = $this->violations();

        if ($issues === []) {
            return;
        }

        $owner = $providerSlug !== null && $providerSlug !== ''
            ? "Feature [{$providerSlug}] declared resource [{$this->key}] with ".count($issues).' schema violation(s):'
            : "Resource [{$this->key}] has ".count($issues).' schema violation(s):';

        throw new InvalidArgumentException($owner.PHP_EOL.implode(PHP_EOL, $issues));
    }

    /**
     * @return array<int, string>
     */
    private function permissionViolations(): array
    {
        $issues = [];

        foreach ($this->permissions as $slot => $slug) {
            if (! in_array($slot, self::PERMISSION_KEYS, true)) {
                $issues[] = "permissions declares unknown slot [{$slot}]";

                continue;
            }

            if (! is_string($slug) || $slug === '') {
                $issues[] = 'permissions.'.$slot.' must be a non-empty permission slug (string), got '
                    .(is_string($slug) ? 'an empty string' : get_debug_type($slug));
            }
        }

        return $issues;
    }

    /**
     * @return array<int, string>
     */
    private function tableViolations(): array
    {
        $issues = [];

        if ($this->table === []) {
            return $issues;
        }

        foreach ($this->table as $key => $value) {
            if (! in_array($key, self::TABLE_KEYS, true)) {
                $issues[] = "table.{$key} is not expressible in a PHP descriptor (TS-only key like bulkActions, or a typo)";
            }
        }

        $columns = $this->table['columns'] ?? null;
        if (! is_array($columns)) {
            $issues[] = 'table.columns must be an array';

            return $issues;
        }

        foreach ($columns as $index => $column) {
            $where = "table.columns[{$index}]";

            if (! is_array($column)) {
                $issues[] = $where.' must be an array';

                continue;
            }

            foreach ($column as $key => $value) {
                if (! in_array($key, self::COLUMN_KEYS, true)) {
                    $issues[] = $where.'.'.$key.' is not expressible in a PHP descriptor (cell is a TS closure)';
                }
            }

            $name = $column['name'] ?? null;
            if (! is_string($name) || $name === '') {
                $issues[] = $where.'.name must be a non-empty accessor key';
            }

            foreach (['header', 'className'] as $stringKey) {
                if (isset($column[$stringKey]) && (! is_string($column[$stringKey]) || $column[$stringKey] === '')) {
                    $issues[] = $where.'.'.$stringKey.' must be a non-empty string';
                }
            }

            foreach (['sortable', 'hideable'] as $boolKey) {
                if (isset($column[$boolKey]) && ! is_bool($column[$boolKey])) {
                    $issues[] = $where.'.'.$boolKey.' must be a boolean';
                }
            }

            if (isset($column['align']) && ! in_array($column['align'], ['left', 'right', 'center'], true)) {
                $issues[] = $where.'.align must be "left", "right" or "center"';
            }
        }

        $rowActions = $this->table['rowActions'] ?? null;
        if ($rowActions !== null) {
            if (! is_array($rowActions)) {
                $issues[] = 'table.rowActions must be an array';
            } else {
                foreach ($rowActions as $index => $action) {
                    $where = "table.rowActions[{$index}]";

                    if (! is_array($action)) {
                        $issues[] = $where.' must be an array';

                        continue;
                    }

                    foreach ($action as $key => $value) {
                        if (! in_array($key, self::ROW_ACTION_KEYS, true)) {
                            $issues[] = $where.'.'.$key.' is not expressible in a PHP descriptor row action';
                        }
                    }

                    $label = $action['label'] ?? null;
                    if (! is_string($label) || $label === '') {
                        $issues[] = $where.'.label must be a non-empty string';
                    }

                    $routeName = $action['routeName'] ?? null;
                    if (! is_string($routeName) || $routeName === '') {
                        $issues[] = $where.'.routeName must be a non-empty route name (the kernel binds the {"record"} param)';
                    }

                    if (isset($action['method']) && ! in_array($action['method'], ['get', 'post', 'put', 'delete'], true)) {
                        $issues[] = $where.'.method must be one of [get|post|put|delete]';
                    }

                    if (isset($action['destructive']) && ! is_bool($action['destructive'])) {
                        $issues[] = $where.'.destructive must be a boolean';
                    }

                    if (isset($action['confirm']) && (! is_string($action['confirm']) || $action['confirm'] === '')) {
                        $issues[] = $where.'.confirm must be a non-empty string when present';
                    }
                }
            }
        }

        foreach (['searchKey', 'searchPlaceholder'] as $stringKey) {
            if (isset($this->table[$stringKey]) && (! is_string($this->table[$stringKey]) || $this->table[$stringKey] === '')) {
                $issues[] = "table.{$stringKey} must be a non-empty string";
            }
        }

        if (isset($this->table['filters'])) {
            foreach ($this->table['filters'] as $index => $filter) {
                $where = "table.filters[{$index}]";

                if (! is_array($filter) || ! is_string($filter['key'] ?? null) || $filter['key'] === '') {
                    $issues[] = $where.' must declare a non-empty string key';

                    continue;
                }

                foreach ($filter['options'] ?? [] as $optionIndex => $option) {
                    if (! is_array($option) || ! is_string($option['label'] ?? null) || ! is_scalar($option['value'] ?? null)) {
                        $issues[] = "$where.options[{$optionIndex}] must have a string label and a scalar value";
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * @return array<int, string>
     */
    private function formViolations(): array
    {
        $issues = [];

        if ($this->form === []) {
            return $issues;
        }

        foreach ($this->form as $index => $section) {
            $where = "form.{$index}";

            if (! is_array($section)) {
                $issues[] = $where.' must be an array';

                continue;
            }

            foreach (['title', 'description'] as $stringKey) {
                if (isset($section[$stringKey]) && (! is_string($section[$stringKey]) || $section[$stringKey] === '')) {
                    $issues[] = "$where.{$stringKey} must be a non-empty string";
                }
            }

            $fields = $section['fields'] ?? null;
            if (! is_array($fields)) {
                $issues[] = $where.' must declare a fields array';

                continue;
            }

            foreach ($fields as $fieldIndex => $field) {
                $fieldWhere = "$where.fields[{$fieldIndex}]";

                if (! is_array($field)) {
                    $issues[] = $fieldWhere.' must be an array';

                    continue;
                }

                foreach ($field as $key => $value) {
                    if (! in_array($key, self::FIELD_KEYS, true)) {
                        $issues[] = $fieldWhere.'.'.$key.' is not expressible in a PHP descriptor (visibleWhen/render/serialize are TS closures)';
                    }
                }

                $name = $field['name'] ?? null;
                if (! is_string($name) || $name === '') {
                    $issues[] = $fieldWhere.'.name must be a non-empty string';
                }

                $type = $field['type'] ?? null;
                if (! is_string($type) || ! in_array($type, self::FIELD_TYPES, true)) {
                    $issues[] = $fieldWhere.'.type must be one of ['.implode('|', self::FIELD_TYPES).']'.' (got '.var_export($type, true).')';
                }

                if ($type === 'relation' && ($field['options'] ?? null) === null && ($field['optionsUrl'] ?? null) === null) {
                    $issues[] = $fieldWhere.' is a relation field and must declare an options source: options or optionsUrl';
                }

                if (isset($field['optionsUrl']) && (! is_string($field['optionsUrl']) || $field['optionsUrl'] === '')) {
                    $issues[] = $fieldWhere.'.optionsUrl must be a non-empty route name (string)';
                }

                foreach (['label', 'help', 'placeholder'] as $stringKey) {
                    if (isset($field[$stringKey]) && (! is_string($field[$stringKey]) || $field[$stringKey] === '')) {
                        $issues[] = "$fieldWhere.{$stringKey} must be a non-empty string";
                    }
                }

                foreach (['required', 'hidden'] as $boolKey) {
                    if (isset($field[$boolKey]) && ! is_bool($field[$boolKey])) {
                        $issues[] = "$fieldWhere.{$boolKey} must be a boolean";
                    }
                }

                if (isset($field['colSpan']) && ! in_array($field['colSpan'], [1, 2, 3, 4], true)) {
                    $issues[] = $fieldWhere.'.colSpan must be 1-4';
                }

                foreach ($field['options'] ?? [] as $optionIndex => $option) {
                    if (! is_array($option) || ! is_string($option['label'] ?? null) || ! is_scalar($option['value'] ?? null)) {
                        $issues[] = "$fieldWhere.options[{$optionIndex}] must have a string label and a scalar value";
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * @return array<int, string>
     */
    private function relationViolations(): array
    {
        $issues = [];

        foreach ($this->relations as $index => $relation) {
            $where = "relations[{$index}]";

            if (! is_array($relation)) {
                $issues[] = $where.' must be an array';

                continue;
            }

            foreach ($relation as $key => $value) {
                if (! in_array($key, self::RELATION_KEYS, true)) {
                    $issues[] = $where.'.'.$key.' is not expressible in a PHP descriptor (component is a TS component)';
                }
            }

            $key = $relation['key'] ?? null;
            if (! is_string($key) || $key === '') {
                $issues[] = $where.'.key must be a non-empty string';
            }

            foreach (['title', 'description'] as $stringKey) {
                if (isset($relation[$stringKey]) && (! is_string($relation[$stringKey]) || $relation[$stringKey] === '')) {
                    $issues[] = "$where.{$stringKey} must be a non-empty string";
                }
            }
        }

        return $issues;
    }
}
