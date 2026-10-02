<?php

use App\Services\FigureMarkets\Concerns\ReadsProviderValues;

beforeEach(function () {
    $this->reader = new class
    {
        use ReadsProviderValues;

        /**
         * @param  array<string, mixed>  $values
         */
        public static function readString(array $values, string $key): ?string
        {
            return self::string($values, $key);
        }

        /**
         * @param  array<string, mixed>  $values
         */
        public static function readInt(array $values, string $key): ?int
        {
            return self::int($values, $key);
        }
    };
});

it('reads a value as a string, or null when absent or null', function (array $values, ?string $expected) {
    expect($this->reader::readString($values, 'price'))->toBe($expected);
})->with([
    'string' => [['price' => '0.019'], '0.019'],
    'float' => [['price' => 0.5], '0.5'],
    'integer' => [['price' => 2], '2'],
    'null' => [['price' => null], null],
    'absent' => [[], null],
]);

it('reads a value as an int, or null when absent or null', function (array $values, ?int $expected) {
    expect($this->reader::readInt($values, 'count'))->toBe($expected);
})->with([
    'string' => [['count' => '16'], 16],
    'integer' => [['count' => 16], 16],
    'null' => [['count' => null], null],
    'absent' => [[], null],
]);
