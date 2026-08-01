<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Count the database queries executed while running the given callback.
     */
    protected function captureQueryCount(callable $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $callback();

        return $count;
    }

    /**
     * Assert that the given callback executes exactly the expected number of DB queries.
     */
    protected function assertQueryCount(int $expected, callable $callback): void
    {
        $count = $this->captureQueryCount($callback);

        $this->assertSame($expected, $count, "Expected {$expected} database queries but {$count} were executed.");
    }
}
