<?php

namespace App\Services\FigureMarkets;

use App\Enums\MarketStatus;
use App\Services\FigureMarkets\Concerns\ReadsProviderValues;

/**
 * A market record from GET /markets, normalized to Market attributes.
 */
final readonly class RestMarketPayload
{
    use ReadsProviderValues;

    public function __construct(
        public string $symbol,
        public ?string $displayName,
        public ?string $baseAsset,
        public ?string $quoteAsset,
        public ?string $marketType,
        public MarketStatus $status,
        public ?int $pricePrecision,
        public ?string $lastPrice,
        public ?string $bestBid,
        public ?string $bestAsk,
        public ?string $priceChange24h,
        public ?string $percentageChange24h,
        public ?string $high24h,
        public ?string $low24h,
        public ?string $volume24h,
        public ?int $tradeCount24h,
    ) {}

    /**
     * @param  array<string, mixed>  $record
     */
    public static function fromRecord(array $record): self
    {
        if (! isset($record['symbol'])) {
            throw MalformedMarketPayload::missing('symbol');
        }

        return new self(
            symbol: $record['symbol'],
            displayName: self::string($record, 'displayName'),
            baseAsset: self::string($record, 'denom'),
            quoteAsset: self::string($record, 'quoteDenom'),
            marketType: self::string($record, 'marketType'),
            status: MarketStatus::fromProvider(self::string($record, 'status') ?? ''),
            pricePrecision: self::int($record, 'pricePrecision'),
            lastPrice: self::string($record, 'lastTradedPrice'),
            bestBid: self::string($record, 'bestBid'),
            bestAsk: self::string($record, 'bestAsk'),
            priceChange24h: self::string($record, 'priceChange24h'),
            percentageChange24h: self::string($record, 'percentageChange24h'),
            high24h: self::string($record, 'high24h'),
            low24h: self::string($record, 'low24h'),
            volume24h: self::string($record, 'volume24h'),
            tradeCount24h: self::int($record, 'tradeCount24h'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'symbol' => $this->symbol,
            'display_name' => $this->displayName,
            'base_asset' => $this->baseAsset,
            'quote_asset' => $this->quoteAsset,
            'market_type' => $this->marketType,
            'status' => $this->status,
            'price_precision' => $this->pricePrecision,
            'last_price' => $this->lastPrice,
            'best_bid' => $this->bestBid,
            'best_ask' => $this->bestAsk,
            'price_change_24h' => $this->priceChange24h,
            'percentage_change_24h' => $this->percentageChange24h,
            'high_24h' => $this->high24h,
            'low_24h' => $this->low24h,
            'volume_24h' => $this->volume24h,
            'trade_count_24h' => $this->tradeCount24h,
        ];
    }
}
