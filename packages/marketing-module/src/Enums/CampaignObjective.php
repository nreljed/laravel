<?php

namespace Nreljed\MarketingModule\Enums;

/**
 * What a campaign is trying to achieve.
 *
 * The objective is reporting metadata, not a publishing rule: it lets an
 * operator group campaigns without pretending the module can measure sales
 * attribution on a social platform.
 */
enum CampaignObjective: string
{
    case Awareness = 'awareness';
    case Engagement = 'engagement';
    case Leads = 'leads';
    case Sales = 'sales';

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
            self::Awareness => 'Awareness',
            self::Engagement => 'Engagement',
            self::Leads => 'Leads',
            self::Sales => 'Sales',
        };
    }
}
