<?php

namespace Nreljed\MarketingModule\Enums;

/**
 * How an insight is handled.
 */
enum MarketingInsightStatus: string
{
    case New = 'new';
    case Applied = 'applied';
    case Dismissed = 'dismissed';

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
            self::New => 'New',
            self::Applied => 'Applied',
            self::Dismissed => 'Dismissed',
        };
    }
}
