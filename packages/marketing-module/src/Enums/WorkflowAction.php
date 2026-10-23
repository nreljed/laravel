<?php

namespace Nreljed\MarketingModule\Enums;

/**
 * What a workflow does when its trigger matches.
 *
 * Audience actions (tag and status) need a contact; a webhook works for both
 * audience and publishing events because it only needs the event payload.
 */
enum WorkflowAction: string
{
    case AddTag = 'add_tag';
    case RemoveTag = 'remove_tag';
    case SetStatus = 'set_status';
    case Webhook = 'webhook';

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
            self::AddTag => 'Add tag',
            self::RemoveTag => 'Remove tag',
            self::SetStatus => 'Set contact status',
            self::Webhook => 'Send webhook',
        };
    }

    /**
     * Whether the action writes to a contact and therefore needs one.
     */
    public function requiresContact(): bool
    {
        return $this !== self::Webhook;
    }
}
