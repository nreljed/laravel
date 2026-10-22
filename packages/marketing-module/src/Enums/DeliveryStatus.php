<?php

namespace Nreljed\MarketingModule\Enums;

/**
 * The lifecycle of one post -> account delivery.
 *
 * A post with five targets owns five of these, and each one retries on its
 * own clock: a Meta outage must not delay the LinkedIn delivery sitting next
 * to it.
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';
    case Skipped = 'skipped';

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
            self::Publishing => 'Publishing',
            self::Published => 'Published',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    /**
     * Whether the delivery has reached a state a retry will not change.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Published, self::Failed, self::Skipped], true);
    }
}
