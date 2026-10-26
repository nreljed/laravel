<?php

namespace Nreljed\MarketingModule\Services;

/**
 * Formats analytics rows as CSV.
 *
 * Spreadsheet applications interpret cells beginning with a formula marker as
 * executable formulas, so string cells are prefixed with an apostrophe. Nulls
 * stay empty; numeric values are emitted verbatim so totals remain numeric.
 */
class AnalyticsCsvExporter
{
    /**
     * @var list<string>
     */
    public const DATASETS = ['posts', 'campaigns', 'links'];

    public function __construct(private readonly MarketingAnalytics $analytics) {}

    /**
     * @return array{filename: string, contents: string}
     */
    public function export(string $dataset, int $days): array
    {
        return [
            'filename' => $this->filename($dataset),
            'contents' => $this->csv($this->headers($dataset), $this->rows($dataset, $days)),
        ];
    }

    public function filename(string $dataset): string
    {
        return sprintf('marketing-%s-%s.csv', $dataset, now()->format('Y-m-d'));
    }

    /**
     * @return callable(): void
     */
    public function stream(string $dataset, int $days): callable
    {
        $headers = $this->headers($dataset);
        $rows = $this->rows($dataset, $days);

        return function () use ($headers, $rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fwrite($output, implode(',', array_map(
                fn (string $header): string => $this->cell($header),
                $headers,
            ))."\r\n");

            foreach ($rows as $row) {
                fwrite($output, implode(',', array_map(
                    fn (mixed $cell): string => $this->cell($cell),
                    $row,
                ))."\r\n");
            }

            fclose($output);
        };
    }

    /**
     * @return list<string>
     */
    public function headers(string $dataset): array
    {
        return match ($dataset) {
            'posts' => [
                'Post ID',
                'Post',
                'Campaign',
                'Status',
                'Platforms',
                'Deliveries',
                'Delivered',
                'Failed',
                'Impressions',
                'Reach',
                'Engagements',
                'Clicks',
                'Engagement rate %',
                'Click-through rate %',
                'Published at',
            ],
            'campaigns' => [
                'Campaign ID',
                'Campaign',
                'Status',
                'Objective',
                'Posts',
                'Deliveries',
                'Delivered',
                'Failed',
                'Delivery rate %',
                'Impressions',
                'Reach',
                'Engagements',
                'Clicks',
                'Engagement rate %',
                'Click-through rate %',
            ],
            'links' => [
                'Link ID',
                'Short URL',
                'Destination',
                'Campaign',
                'Clicks',
                'Conversions',
                'Last clicked at',
            ],
            default => [],
        };
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    private function csv(array $headers, array $rows): string
    {
        $lines = [$this->line($headers)];

        foreach ($rows as $row) {
            $lines[] = $this->line($row);
        }

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * @return list<list<mixed>>
     */
    private function rows(string $dataset, int $days): array
    {
        $rows = $this->analytics->dataset($dataset, $days);

        return match ($dataset) {
            'posts' => array_map(static fn (array $row): array => [
                $row['id'] ?? null,
                $row['body'] ?? null,
                $row['campaign'] ?? null,
                $row['status_label'] ?? null,
                implode(', ', $row['platforms'] ?? []),
                $row['deliveries'] ?? 0,
                $row['delivered'] ?? 0,
                $row['failed'] ?? 0,
                $row['impressions'] ?? null,
                $row['reach'] ?? null,
                $row['engagements'] ?? null,
                $row['clicks'] ?? null,
                $row['engagement_rate'] ?? null,
                $row['click_through_rate'] ?? null,
                $row['published_at'] ?? null,
            ], $rows),
            'campaigns' => array_map(static fn (array $row): array => [
                $row['id'] ?? null,
                $row['name'] ?? null,
                $row['status_label'] ?? null,
                $row['objective_label'] ?? null,
                $row['posts'] ?? 0,
                $row['deliveries'] ?? 0,
                $row['delivered'] ?? 0,
                $row['failed'] ?? 0,
                $row['delivery_rate'] ?? null,
                $row['impressions'] ?? null,
                $row['reach'] ?? null,
                $row['engagements'] ?? null,
                $row['clicks'] ?? null,
                $row['engagement_rate'] ?? null,
                $row['click_through_rate'] ?? null,
            ], $rows),
            'links' => array_map(static fn (array $row): array => [
                $row['id'] ?? null,
                $row['short_url'] ?? null,
                $row['destination_url'] ?? null,
                $row['utm_campaign'] ?? null,
                $row['clicks'] ?? 0,
                $row['conversions'] ?? 0,
                $row['last_clicked_at'] ?? null,
            ], $rows),
            default => [],
        };
    }

    private function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $value = (string) $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'".$value;
        }

        return '"'.str_replace('"', '""', $value).'"';
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function line(array $cells): string
    {
        return implode(',', array_map(
            fn (mixed $cell): string => $this->cell($cell),
            $cells,
        ));
    }
}
