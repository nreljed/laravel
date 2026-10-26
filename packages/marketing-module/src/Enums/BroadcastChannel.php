<?php

namespace Nreljed\MarketingModule\Enums;

enum BroadcastChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';

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
            self::Email => 'Email',
            self::Sms => 'SMS',
            self::WhatsApp => 'WhatsApp',
        };
    }
}
