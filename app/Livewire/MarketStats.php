<?php

namespace App\Livewire;

use App\Models\Market;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read ?Market $market
 */
class MarketStats extends Component
{
    private const string MISSING = '—';

    private const int VOLUME_DECIMALS = 8;

    public string $symbol;

    #[Computed]
    public function market(): ?Market
    {
        return Market::firstWhere('symbol', $this->symbol);
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $market = $this->market;

        return $market === null ? [] : ["echo-private:markets.{$market->id},.market.updated" => '$refresh'];
    }

    public function render(): View
    {
        $market = $this->market;

        return view('livewire.market-stats', [
            'stats' => $market === null ? [] : $this->stats($market),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function stats(Market $market): array
    {
        $precision = $market->price_precision;

        return [
            'Last price' => $this->formatPrice($market->last_price, $precision),
            '24h change' => $this->formatPrice($market->price_change_24h, $precision),
            '24h change %' => $this->formatPercentage($market->percentage_change_24h),
            'Bid' => $this->formatPrice($market->best_bid, $precision),
            'Ask' => $this->formatPrice($market->best_ask, $precision),
            '24h high' => $this->formatPrice($market->high_24h, $precision),
            '24h low' => $this->formatPrice($market->low_24h, $precision),
            '24h volume' => $this->formatVolume($market->volume_24h),
            '24h trades' => $this->formatCount($market->trade_count_24h),
            'Last updated' => $this->formatTimestamp($market->price_updated_at),
        ];
    }

    private function formatPrice(?string $value, int $precision): string
    {
        return $value === null ? self::MISSING : number_format((float) $value, $precision);
    }

    private function formatPercentage(?string $ratio): string
    {
        return $ratio === null ? self::MISSING : number_format((float) $ratio * 100, 2).'%';
    }

    private function formatVolume(?string $value): string
    {
        if ($value === null) {
            return self::MISSING;
        }

        return rtrim(rtrim(number_format((float) $value, self::VOLUME_DECIMALS), '0'), '.');
    }

    private function formatCount(?int $value): string
    {
        return $value === null ? self::MISSING : number_format($value);
    }

    private function formatTimestamp(?CarbonImmutable $value): string
    {
        return $value === null ? self::MISSING : $value->utc()->format('Y-m-d H:i:s').' UTC';
    }
}
