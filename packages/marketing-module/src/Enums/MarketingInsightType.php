<?php

namespace Nreljed\MarketingModule\Enums;

/**
 * The kind of recommendation an insight carries.
 *
 * The type is a stable vocabulary: the UI groups by it and an applied insight
 * uses it to decide which draft object to create.
 */
enum MarketingInsightType: string
{
    case Coverage = 'coverage';
    case Delivery = 'delivery';
    case Engagement = 'engagement';
    case ClickThrough = 'click_through';
    case Content = 'content';
    case Conversion = 'conversion';

    public function label(): string
    {
        return match ($this) {
            self::Coverage => 'Coverage',
            self::Delivery => 'Delivery',
            self::Engagement => 'Engagement',
            self::ClickThrough => 'Click-through',
            self::Content => 'Content',
            self::Conversion => 'Conversion',
        };
    }
}
