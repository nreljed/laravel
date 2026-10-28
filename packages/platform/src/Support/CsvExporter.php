<?php

namespace Nreljed\Platform\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared CSV streaming helper.
 *
 * Every export in the fleet previously re-implemented `fputcsv` over
 * `php://output`. Two things were always missing: streaming the header once
 * and, more importantly, guarding against CSV formula injection (CWE-1236).
 * A cell beginning with =, +, -, @, a tab or a carriage return is evaluated as
 * a formula by Excel and Google Sheets, which is how a message body becomes a
 * command on an admin's machine.
 *
 * escapeCell() neutralises that prefix while leaving genuine negative numbers
 * alone, and the column order is always the caller's to own so downstream
 * spreadsheets do not break when a form gains a field.
 */
class CsvExporter
{
    /**
     * Leading characters Excel/Sheets treat as the start of a formula.
     *
     * @var array<int, string>
     */
    public const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Stream a CSV download. The writer receives this instance and calls
     * writeLine() — once for the header, then once per chunk of rows.
     *
     * @param  callable(self): void  $writer
     */
    public function stream(string $filename, callable $writer): StreamedResponse
    {
        return response()->stream(function () use ($writer) {
            $handle = fopen('php://output', 'w');

            try {
                $writer($this->withHandle($handle));
            } finally {
                fclose($handle);
            }
        }, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Neutralise a leading formula character so spreadsheet apps treat the cell
     * as literal text. Numeric values (including negatives) are left untouched.
     */
    public static function escapeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $value = (string) $value;

        if ($value === '' || is_numeric($value)) {
            return $value;
        }

        if (in_array($value[0], self::FORMULA_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }

    protected mixed $handle = null;

    protected function withHandle(mixed $handle): static
    {
        $this->handle = $handle;

        return $this;
    }

    /**
     * Write one sanitized row.
     *
     * @param  array<int, mixed>  $cells
     */
    public function writeLine(array $cells): void
    {
        fputcsv(
            $this->handle,
            array_map([self::class, 'escapeCell'], $cells),
            escape: '\\',
        );
    }
}
