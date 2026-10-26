<?php

namespace Nreljed\MarketingModule\Enums;

/**
 * The lifecycle of a content brief.
 *
 * A brief is editable planning material. Marking it ready says an operator has
 * reviewed it; it still cannot publish anything.
 */
enum MarketingBriefStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Used = 'used';
    case Archived = 'archived';

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
            self::Draft => 'Draft',
            self::Ready => 'Ready',
            self::Used => 'Used',
            self::Archived => 'Archived',
        };
    }
}
