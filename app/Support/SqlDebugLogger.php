<?php

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;

class SqlDebugLogger
{
    public static function handle(QueryExecuted $query): void
    {
        return;
    }

    public static function logManualQuery(string $sql, array $bindings = [], ?float $timeMs = null): void
    {
        return;
    }

    public static function reset(): void
    {
        return;
    }

    public static function withoutLogging(callable $callback)
    {
        return $callback();
    }

    public static function isEnabled(): bool
    {
        return false;
    }
}
