<?php

namespace Database\Factories;

use App\Enums\MarketStatus;
use App\Models\Market;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Market>
 */
class MarketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseAsset = strtoupper(fake()->unique()->lexify('????'));
        $symbol = "{$baseAsset}-USD";
        $lastPrice = fake()->randomFloat(8, 0.01, 1000);

        return [
            'symbol' => $symbol,
            'display_name' => $symbol,
            'base_asset' => $baseAsset,
            'quote_asset' => 'USD',
            'market_type' => 'CRYPTO',
            'status' => MarketStatus::Open,
            'price_precision' => fake()->numberBetween(2, 8),
            'last_price' => $lastPrice,
            'best_bid' => $lastPrice * 0.99,
            'best_ask' => $lastPrice * 1.01,
            'price_change_24h' => fake()->randomFloat(8, -10, 10),
            'percentage_change_24h' => fake()->randomFloat(6, -0.1, 0.1),
            'high_24h' => $lastPrice * 1.05,
            'low_24h' => $lastPrice * 0.95,
            'volume_24h' => fake()->randomFloat(8, 0, 1_000_000),
            'trade_count_24h' => fake()->numberBetween(1, 5000),
            'price_updated_at' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MarketStatus::Closed,
        ]);
    }

    public function withoutOrderBook(): static
    {
        return $this->state(fn (array $attributes) => [
            'best_bid' => null,
            'best_ask' => null,
        ]);
    }

    public function untraded(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_price' => 0,
            'volume_24h' => 0,
            'trade_count_24h' => 0,
        ]);
    }
}
