<?php

namespace App\Services\FigureMarkets\Concerns;

trait ReadsProviderValues
{
    /**
     * Numbers arrive as strings, floats or ints; the model casts strings, so no float reaches the database.
     *
     * @param  array<string, mixed>  $values
     */
    private static function string(array $values, string $key): ?string
    {
        return isset($values[$key]) ? (string) $values[$key] : null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function int(array $values, string $key): ?int
    {
        return isset($values[$key]) ? (int) $values[$key] : null;
    }
}
