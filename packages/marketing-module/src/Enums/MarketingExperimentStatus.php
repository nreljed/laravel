<?php

namespace Nreljed\MarketingModule\Enums;

/**
 * The lifecycle of a content experiment.
 *
 * Success metrics are chosen before a run starts. Promotion is an operator
 * decision, never an automatic publish.
 */
enum MarketingExperimentStatus: string
{
    case Draft = 'draft';
    case Running = 'running';
    case Completed = 'completed';
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
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
