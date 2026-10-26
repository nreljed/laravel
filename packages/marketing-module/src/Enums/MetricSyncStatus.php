<?php

namespace Nreljed\MarketingModule\Enums;

/**
 * The latest metric-sync attempt for one delivery.
 *
 * `unavailable` is a successful request with no usable numbers; `error` is a
 * failed request that may be retried; `dead_letter` is a failure that will not
 * be retried until an operator replays it. None is represented by a zero row.
 */
enum MetricSyncStatus: string
{
    case Pending = 'pending';
    case Ok = 'ok';
    case Unavailable = 'unavailable';
    case Error = 'error';
    case DeadLetter = 'dead_letter';

    /**
     * @return list<array{label: string, value: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['label' => $case->label(), 'value' => $case->value],
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Ok => 'Synced',
            self::Unavailable => 'Unavailable',
            self::Error => 'Error',
            self::DeadLetter => 'Dead letter',
        };
    }
}
