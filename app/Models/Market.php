<?php

namespace App\Models;

use App\Enums\MarketStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MarketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $symbol
 * @property string $display_name
 * @property string $base_asset
 * @property string $quote_asset
 * @property string $market_type
 * @property MarketStatus $status
 * @property int $price_precision
 * @property string|null $last_price
 * @property string|null $best_bid
 * @property string|null $best_ask
 * @property string|null $price_change_24h
 * @property string|null $percentage_change_24h
 * @property string|null $high_24h
 * @property string|null $low_24h
 * @property string|null $volume_24h
 * @property int|null $trade_count_24h
 * @property CarbonImmutable|null $price_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'symbol',
    'display_name',
    'base_asset',
    'quote_asset',
    'market_type',
    'status',
    'price_precision',
    'last_price',
    'best_bid',
    'best_ask',
    'price_change_24h',
    'percentage_change_24h',
    'high_24h',
    'low_24h',
    'volume_24h',
    'trade_count_24h',
    'price_updated_at',
])]
#[Table(dateFormat: 'Y-m-d H:i:s.u')]
class Market extends Model
{
    /** @use HasFactory<MarketFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MarketStatus::class,
            'price_precision' => 'int',
            'last_price' => 'decimal:18',
            'best_bid' => 'decimal:18',
            'best_ask' => 'decimal:18',
            'price_change_24h' => 'decimal:18',
            'percentage_change_24h' => 'decimal:6',
            'high_24h' => 'decimal:18',
            'low_24h' => 'decimal:18',
            'volume_24h' => 'decimal:18',
            'trade_count_24h' => 'int',
            'price_updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<Market>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', MarketStatus::Open);
    }
}
