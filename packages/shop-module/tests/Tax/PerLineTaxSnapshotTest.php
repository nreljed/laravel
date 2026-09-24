<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tests\Tax;

use Nreljed\ShopModule\Tax\PerLineTaxResolver;
use Nreljed\ShopModule\Tax\PerLineTaxSnapshot;
use PHPUnit\Framework\TestCase;

final class PerLineTaxSnapshotTest extends TestCase
{
    public function test_standard_class_without_zone_uses_class_default_rate(): void
    {
        $snapshot = PerLineTaxResolver::defaults()->snapshot(
            'sku-1',
            ['sku' => 'sku-1', 'subtotal' => 100.0],
            'standard',
        );

        self::assertInstanceOf(PerLineTaxSnapshot::class, $snapshot);
        self::assertSame('sku-1', $snapshot->lineCode);
        self::assertSame('standard', $snapshot->taxClassCode);
        self::assertNull($snapshot->taxZoneCode);
        self::assertSame(0.19, $snapshot->rate);
        self::assertSame(100.0, $snapshot->taxableAmount);
        self::assertSame(19.0, $snapshot->taxAmount);
    }

    public function test_reduced_class_in_eu_zone_uses_zone_rate(): void
    {
        $snapshot = PerLineTaxResolver::defaults()->snapshot(
            'sku-2',
            ['sku' => 'sku-2', 'subtotal' => 200.0],
            'reduced',
            'FR',
        );

        self::assertSame('eu', $snapshot->taxZoneCode);
        self::assertSame(0.07, $snapshot->rate);
        self::assertSame(200.0, $snapshot->taxableAmount);
        self::assertSame(14.0, $snapshot->taxAmount);
    }

    public function test_zero_rated_line_stays_zero(): void
    {
        $snapshot = PerLineTaxResolver::defaults()->snapshot(
            'sku-3',
            ['sku' => 'sku-3', 'subtotal' => 10.0],
            'zero',
        );

        self::assertSame('zero', $snapshot->taxClassCode);
        self::assertSame(0.0, $snapshot->rate);
        self::assertSame(0.0, $snapshot->taxAmount);
    }

    public function test_snapshot_to_array_exposes_snapshot_fields(): void
    {
        $snapshot = PerLineTaxResolver::defaults()->snapshot(
            'sku-4',
            ['sku' => 'sku-4', 'subtotal' => 50.0],
            'standard',
            'US',
            'CA',
        );

        self::assertSame([
            'line_code' => 'sku-4',
            'tax_class_code' => 'standard',
            'tax_zone_code' => 'us',
            'rate' => 0.0725,
            'taxable_amount' => 50.0,
            'tax_amount' => 3.63,
        ], $snapshot->toArray());
    }
}
