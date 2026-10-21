<?php

namespace Nreljed\Platform\Support;

use InvalidArgumentException;

/**
 * Resolves the form catalog declared in config('forms.forms').
 *
 * A form is pure configuration: its field list is simultaneously the public
 * validation contract, the admin inbox layout and the stable CSV column order.
 * Nothing about a form is hard-coded in a controller, so a new form costs a
 * config entry and a front-end form that POSTs to /forms/{key}.
 *
 * Runtime registration (Forms::register()) lets a feature provider contribute
 * a form from boot() without touching the FeatureProvider contract. Config
 * entries always win on key collision so a module can never shadow a core form.
 */
class FormRegistry
{
    protected ?array $forms = null;

    protected array $runtime = [];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        if ($this->forms === null) {
            $config = config('forms.forms', []);

            $this->forms = [];

            foreach (is_array($config) ? $config : [] as $key => $definition) {
                if (! is_string($key) || ! is_array($definition)) {
                    continue;
                }

                $this->forms[$key] = $this->withDefaults($definition);
            }

            foreach ($this->runtime as $key => $definition) {
                if (! isset($this->forms[$key])) {
                    $this->forms[$key] = $this->withDefaults($definition);
                }
            }
        }

        return $this->forms;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * Labels keyed by form key, for admin filter dropdowns.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        $labels = [];

        foreach ($this->all() as $key => $definition) {
            $labels[$key] = (string) ($definition['label'] ?? $key);
        }

        return $labels;
    }

    /**
     * Contribute a form at runtime. Ignored when the config already owns the key.
     *
     * @param  array<string, mixed>  $definition
     */
    public function register(string $key, array $definition): void
    {
        $this->runtime[$key] = $definition;
        $this->forms = null;
    }

    /**
     * Fail loudly when a form key is not declared.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $key): array
    {
        $definition = $this->get($key);

        if ($definition === null) {
            throw new InvalidArgumentException(
                "No form is registered under key [{$key}]. Declare it in config/forms.php or via Forms::register()."
            );
        }

        return $definition;
    }

    /**
     * Build Laravel validation rules from a form's declared fields plus its
     * consent requirement. The honeypot is deliberately absent: it is checked
     * before validation so a bot never learns which field trapped it.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, array<int, string>>
     */
    public function rules(array $definition): array
    {
        $rules = [];

        foreach ($this->fields($definition) as $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $fieldRules = [];

            $fieldRules[] = ($field['required'] ?? false) ? 'required' : 'nullable';

            // `email` and `url` already constrain the value to a string, so
            // adding a bare `string` rule alongside them is redundant noise in
            // the generated rule set.
            $type = $field['type'] ?? 'text';

            if ($type === 'email') {
                $fieldRules[] = 'email';
            } elseif ($type === 'url') {
                $fieldRules[] = 'url';
            } else {
                $fieldRules[] = 'string';
            }

            if (isset($field['min'])) {
                $fieldRules[] = 'min:'.(int) $field['min'];
            }

            if (isset($field['max'])) {
                $fieldRules[] = 'max:'.(int) $field['max'];
            }

            $rules[$name] = $fieldRules;
        }

        $consent = $definition['consent'] ?? null;

        if (is_array($consent)) {
            $rules['consent'] = [! empty($consent['required']) ? 'accepted' : 'nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * Human labels for validation messages, keyed by field name.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, string>
     */
    public function attributes(array $definition): array
    {
        $attributes = [];

        foreach ($this->fields($definition) as $field) {
            if (! empty($field['name']) && ! empty($field['label'])) {
                $attributes[$field['name']] = (string) $field['label'];
            }
        }

        return $attributes;
    }

    /**
     * Ordered field definitions. This order is the CSV column order.
     *
     * @param  array<string, mixed>  $definition
     * @return array<int, array<string, mixed>>
     */
    public function fields(array $definition): array
    {
        $fields = $definition['fields'] ?? [];

        return is_array($fields) ? array_values($fields) : [];
    }

    /**
     * payload keys the inbox search should cover: every declared field plus any
     * extra `searchable` entries.
     *
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    public function searchable(array $definition): array
    {
        $keys = [];

        foreach ($this->fields($definition) as $field) {
            if (! empty($field['name'])) {
                $keys[] = (string) $field['name'];
            }
        }

        $extra = $definition['searchable'] ?? [];

        foreach (is_array($extra) ? $extra : [] as $key) {
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Denormalised column => payload key mapping for name / email / summary.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, string>
     */
    public function identity(array $definition): array
    {
        $identity = $definition['identity'] ?? [];

        return is_array($identity) ? $identity : [];
    }

    /**
     * The inbox preview line for a submission.
     *
     * Derived from the first declared `textarea` field rather than config: a
     * separate mapping would be one more thing to keep in sync, and every
     * form's long-form body is exactly the text an operator wants to see
     * under the summary. Returns null when the form has no textarea.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $payload
     */
    public function excerpt(array $definition, array $payload): ?string
    {
        $source = null;

        foreach ($this->fields($definition) as $field) {
            if (($field['type'] ?? null) === 'textarea' && ! empty($field['name'])) {
                $source = (string) $field['name'];
                break;
            }
        }

        if ($source === null) {
            return null;
        }

        $value = $payload[$source] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Retention horizon for this form's ARCHIVED submissions.
     *
     * @param  array<string, mixed>  $definition
     */
    public function retentionDays(array $definition): int
    {
        $days = (int) ($definition['retention_days'] ?? 365);

        return $days > 0 ? $days : 365;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    public function violations(array $definition): array
    {
        $issues = [];

        if (empty($definition['label']) || ! is_string($definition['label'])) {
            $issues[] = 'label must be a non-empty string.';
        }

        if (empty($definition['permission']) || ! is_string($definition['permission'])) {
            $issues[] = 'permission must be a non-empty permission slug.';
        }

        $redirect = $definition['redirect'] ?? null;

        if ($redirect !== null && (! is_string($redirect) || $redirect === '')) {
            $issues[] = 'redirect must be a route name or null.';
        }

        $fields = $definition['fields'] ?? [];

        if (! is_array($fields) || $fields === []) {
            $issues[] = 'fields must declare at least one field.';

            return $issues;
        }

        $seen = [];

        foreach ($fields as $index => $field) {
            $where = "fields[{$index}]";

            if (! is_array($field)) {
                $issues[] = "{$where} must be an array.";

                continue;
            }

            if (empty($field['name']) || ! is_string($field['name'])) {
                $issues[] = "{$where}.name must be a non-empty string.";

                continue;
            }

            if (isset($seen[$field['name']])) {
                $issues[] = "{$where}.name [{$field['name']}] is declared twice.";
            }

            $seen[$field['name']] = true;

            if (($field['type'] ?? 'text') === 'email' && ($field['name'] !== 'email') && empty($field['label'])) {
                $issues[] = "{$where} email fields need a label.";
            }
        }

        $identity = $definition['identity'] ?? [];

        if (! is_array($identity)) {
            $issues[] = 'identity must be an array of column => field mappings.';
        } else {
            foreach ($identity as $column => $source) {
                if (! in_array($column, ['name', 'email', 'summary'], true)) {
                    $issues[] = "identity column [{$column}] must be one of name|email|summary.";
                }

                if (! is_string($source) || ! isset($seen[$source])) {
                    $issues[] = "identity column [{$column}] maps to [".(is_string($source) ? $source : 'non-string').'] which is not a declared field.';
                }
            }
        }

        $honeypot = $definition['honeypot'] ?? null;

        if ($honeypot !== null && (! is_string($honeypot) || $honeypot === '')) {
            $issues[] = 'honeypot must be a field name or null.';
        }

        if (isset($seen[$honeypot])) {
            $issues[] = 'honeypot must not collide with a declared field.';
        }

        $notify = $definition['notify'] ?? null;

        if ($notify !== null && (! is_string($notify) || $notify === '')) {
            $issues[] = 'notify must be a notification type key or null.';
        }

        $consent = $definition['consent'] ?? null;

        if ($consent !== null && ! is_array($consent)) {
            $issues[] = 'consent must be an array or null.';
        } elseif (is_array($consent) && empty($consent['label'])) {
            $issues[] = 'consent declarations need a label (the statement shown to the visitor).';
        }

        if (isset($seen['consent'])) {
            $issues[] = 'consent is a reserved field name and cannot be declared.';
        }

        $limit = $definition['rate_limit'] ?? null;

        if ($limit !== null && (! is_string($limit) || ! preg_match('/^\d+,\d+$/', $limit))) {
            $issues[] = 'rate_limit must look like "3,1".';
        }

        foreach (['retention_days', 'dedupe_window', 'anonymise_after_days'] as $numeric) {
            if (isset($definition[$numeric]) && ! is_int($definition[$numeric]) && ! ctype_digit((string) $definition[$numeric])) {
                $issues[] = "{$numeric} must be an integer.";
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    protected function withDefaults(array $definition): array
    {
        return array_merge([
            'description' => '',
            'redirect' => null,
            'honeypot' => null,
            'rate_limit' => '3,1',
            'dedupe_window' => 600,
            'notify' => null,
            'consent' => null,
            'searchable' => [],
            'retention_days' => 365,
            'anonymise_after_days' => null,
            'identity' => [],
        ], $definition);
    }
}
