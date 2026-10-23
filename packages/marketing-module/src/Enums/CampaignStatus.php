<?php

namespace Nreljed\MarketingModule\Enums;

/**
 * The lifecycle of a campaign.
 *
 * A campaign is a grouping of posts around one objective; it does not publish
 * anything itself. The status is what the report uses to separate work in
 * progress from what actually ran.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
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
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }
}
