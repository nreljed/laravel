<?php

namespace Nreljed\MarketingModule\Enums;

/**
 * The approval lifecycle of a marketing plan.
 *
 * A plan never publishes or spends on its own. Approval is what turns a draft
 * into the document the module may use to create campaigns and briefs.
 */
enum MarketingPlanStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
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
            self::Approved => 'Approved',
            self::Archived => 'Archived',
        };
    }
}
