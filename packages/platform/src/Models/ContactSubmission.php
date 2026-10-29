<?php

namespace Nreljed\Platform\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A message submitted through the public contact form.
 *
 * Submissions are never deleted automatically; admins triage them from the
 * dashboard (new -> read -> archived). The admin notification is dispatched on
 * create under the `contact.received` notification type.
 */
class ContactSubmission extends Model
{
    public const string STATUS_NEW = 'new';

    public const string STATUS_READ = 'read';

    public const string STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'ip',
        'user_agent',
        'status',
        'read_at',
    ];

    protected $casts = [
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

    public function markAsRead(): void
    {
        if ($this->status !== self::STATUS_NEW) {
            return;
        }

        $this->forceFill([
            'status' => self::STATUS_READ,
            'read_at' => now(),
        ])->save();
    }
}
