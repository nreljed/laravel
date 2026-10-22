<?php

namespace Nreljed\MarketingModule\Enums;

/**
 * The lifecycle of a social post.
 *
 * `partial` is deliberately its own state rather than a flavour of `published`
 * or `failed`: a post that reached three of five platforms is neither, and an
 * operator needs to see which targets still owe a delivery.
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Publishing = 'publishing';
    case Published = 'published';
    case Partial = 'partial';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

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
            self::Scheduled => 'Scheduled',
            self::Publishing => 'Publishing',
            self::Published => 'Published',
            self::Partial => 'Partially published',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether the post has reached a state no scheduler run will change.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Published, self::Partial, self::Failed, self::Cancelled], true);
    }
}
