<?php

namespace Nreljed\MarketingModule\Enums;

enum ContactStatus: string
{
    case Lead = 'lead';
    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';

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
            self::Lead => 'Lead',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Blocked => 'Blocked',
        };
    }
}
