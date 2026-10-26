<?php

namespace Nreljed\MarketingModule\Enums;

/**
 * Confidence attached to a recommendation.
 */
enum MarketingInsightConfidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
        };
    }
}
