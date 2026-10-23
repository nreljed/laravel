<?php

namespace Nreljed\MarketingModule\Enums;

/**
 * The outcome of one workflow execution.
 */
enum WorkflowRunStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';

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
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }
}
