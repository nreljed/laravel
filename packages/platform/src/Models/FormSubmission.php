<?php

namespace Nreljed\Platform\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A submission from any declared public form.
 *
 * One table carries every form: `form_key` discriminates, `name`/`email`/
 * `summary` are denormalised from the registry's identity mapping so the inbox
 * list, search and DSAR-by-email lookups stay indexed, and the complete field
 * payload lives in `payload` as JSON. Adding a form never adds a table.
 *
 * Submissions are never deleted automatically by the request lifecycle; admins
 * triage them (new -> read -> archived) and `app:prune-form-submissions` enforces
 * the retention rules declared in config/forms.php.
 */
class FormSubmission extends Model
{
    public const string STATUS_NEW = 'new';

    public const string STATUS_READ = 'read';

    public const string STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'form_key',
        'name',
        'email',
        'summary',
        'payload',
        'status',
        'ip',
        'user_agent',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'consent_at',
        'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'consent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_READ,
            self::STATUS_ARCHIVED,
        ];
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $query->when(
            $status !== null && in_array($status, static::statuses(), true),
            fn (Builder $builder) => $builder->where('status', $status),
        );
    }

    public function scopeFormKey(Builder $query, ?string $formKey): Builder
    {
        return $query->when(
            is_string($formKey) && $formKey !== '',
            fn (Builder $builder) => $builder->where('form_key', $formKey),
        );
    }

    /**
     * Free-text search across the denormalised inbox columns and the raw JSON
     * payload, which covers every declared field at once.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! is_string($term) || trim($term) === '') {
            return $query;
        }

        $like = '%'.trim($term).'%';

        return $query->where(function (Builder $builder) use ($like) {
            $builder->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('summary', 'like', $like)
                ->orWhere('payload', 'like', $like);
        });
    }

    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when(is_string($from) && $from !== '', fn (Builder $builder) => $builder->where('created_at', '>=', $from))
            ->when(is_string($to) && $to !== '', fn (Builder $builder) => $builder->where('created_at', '<=', $to.' 23:59:59'));
    }

    public function scopeOrdered(Builder $query, ?string $sort, ?string $direction): Builder
    {
        $sortable = ['created_at', 'name', 'email', 'summary', 'status'];
        $column = in_array($sort, $sortable, true) ? $sort : 'created_at';

        return $query->orderBy($column, strtolower($direction) === 'asc' ? 'asc' : 'desc');
    }

    public function markAsRead(): void
    {
        if ($this->status !== self::STATUS_NEW) {
            return;
        }

        $this->forceFill([
            'status' => self::STATUS_READ,
            'read_at' => $this->read_at ?? now(),
        ])->save();
    }

    /**
     * A single payload value, with the identity column as fallback.
     */
    public function field(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }
}
