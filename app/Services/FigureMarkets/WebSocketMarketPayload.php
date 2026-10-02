<?php

namespace App\Services\FigureMarkets;

use App\Services\FigureMarkets\Concerns\ReadsProviderValues;
use Carbon\CarbonImmutable;

/**
 * A MARKET channel message, normalized to the live Market attributes.
 * Identity columns come from REST only; status and pricePrecision are ignored here.
 */
final readonly class WebSocketMarketPayload
{
    use ReadsProviderValues;

    public function __construct(
        public string $symbol,
        public ?string $lastPrice,
        public ?string $bestBid,
        public ?string $bestAsk,
        public ?string $priceChange24h,
        public ?string $percentageChange24h,
        public ?string $high24h,
        public ?string $low24h,
        public ?string $volume24h,
        public ?int $tradeCount24h,
        public CarbonImmutable $publishedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $message
     */
    public static function fromMessage(array $message): self
    {
        if (! isset($message['marketId'])) {
            throw MalformedMarketPayload::missing('marketId');
        }

        if (! isset($message['publishTime'])) {
            throw MalformedMarketPayload::missing('publishTime');
        }

        return new self(
            symbol: $message['marketId'],
            lastPrice: self::string($message, 'lastTradedPrice'),
            bestBid: self::string($message, 'bestBid'),
            bestAsk: self::string($message, 'bestAsk'),
            priceChange24h: self::string($message, 'priceChange24h'),
            percentageChange24h: self::string($message, 'percentageChange24h'),
            high24h: self::string($message, 'high24h'),
            low24h: self::string($message, 'low24h'),
            volume24h: self::string($message, 'volume24h'),
            tradeCount24h: self::int($message, 'tradeCount24h'),
            publishedAt: CarbonImmutable::parse($message['publishTime']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'last_price' => $this->lastPrice,
            'best_bid' => $this->bestBid,
            'best_ask' => $this->bestAsk,
            'price_change_24h' => $this->priceChange24h,
            'percentage_change_24h' => $this->percentageChange24h,
            'high_24h' => $this->high24h,
            'low_24h' => $this->low24h,
            'volume_24h' => $this->volume24h,
            'trade_count_24h' => $this->tradeCount24h,
            'price_updated_at' => $this->publishedAt,
        ];
    }
}
