<?php

use App\Livewire\MarketStats;
use App\Models\Market;
use Livewire\Livewire;

it('renders every stat formatted at the market price precision', function () {
    $market = Market::factory()->create([
        'price_precision' => 4,
        'last_price' => '1234.56789',
        'price_change_24h' => '-0.5',
        'percentage_change_24h' => '-0.05',
        'best_bid' => '1234.5',
        'best_ask' => '1234.6',
        'high_24h' => '1300',
        'low_24h' => '1200',
        'volume_24h' => '242099.369104841718',
        'trade_count_24h' => 12345,
        'price_updated_at' => '2026-10-02 10:37:55.734000',
    ]);

    Livewire::test(MarketStats::class, ['symbol' => $market->symbol])
        ->assertSeeInOrder([
            '1,234.5679',
            '-0.5000',
            '-5.00%',
            '1,234.5000',
            '1,234.6000',
            '1,300.0000',
            '1,200.0000',
            '242,099.36910484',
            '12,345',
            '2026-10-02 10:37:55 UTC',
        ]);
});

it('trims trailing zeros from the volume', function (string $volume, string $expected) {
    $market = Market::factory()->create(['volume_24h' => $volume]);

    Livewire::test(MarketStats::class, ['symbol' => $market->symbol])
        ->assertViewHas('stats', fn (array $stats) => $stats['24h volume'] === $expected);
})->with([
    'fraction' => ['1000.5', '1,000.5'],
    'whole number' => ['1000', '1,000'],
]);

it('renders a dash for a missing bid, ask and timestamp', function () {
    $market = Market::factory()->withoutOrderBook()->create(['price_updated_at' => null]);

    Livewire::test(MarketStats::class, ['symbol' => $market->symbol])
        ->assertViewHas('stats', fn (array $stats) => $stats['Bid'] === '—'
            && $stats['Ask'] === '—'
            && $stats['Last updated'] === '—');
});

it('renders a formatted zero for an untraded market', function () {
    $market = Market::factory()->untraded()->create(['price_precision' => 4]);

    Livewire::test(MarketStats::class, ['symbol' => $market->symbol])
        ->assertViewHas('stats', fn (array $stats) => $stats['Last price'] === '0.0000'
            && $stats['24h volume'] === '0'
            && $stats['24h trades'] === '0');
});

it('re-renders with the new price on its market event', function () {
    $market = Market::factory()->create(['price_precision' => 2, 'last_price' => '1.00']);
    $component = Livewire::test(MarketStats::class, ['symbol' => $market->symbol])->assertSee('1.00');
    $market->update(['last_price' => '2.00']);

    $component->dispatch("echo-private:markets.{$market->id},.market.updated")
        ->assertSee('2.00');
});

it('subscribes to its own market channel only', function () {
    $market = Market::factory()->create();

    $listeners = Livewire::test(MarketStats::class, ['symbol' => $market->symbol])->instance()->getListeners();

    expect($listeners)->toBe(["echo-private:markets.{$market->id},.market.updated" => '$refresh']);
});

it('leaves its market channel when the card is removed', function () {
    $market = Market::factory()->create();

    Livewire::test(MarketStats::class, ['symbol' => $market->symbol])
        ->assertSeeHtml("window.Echo.leave('markets.{$market->id}')");
});

it('subscribes to nothing for an unknown symbol', function () {
    $component = Livewire::test(MarketStats::class, ['symbol' => 'NONE-USD'])
        ->assertDontSeeHtml('window.Echo.leave');

    expect($component->instance()->getListeners())->toBe([]);
});

it('renders no stats for an unknown symbol', function () {
    Livewire::test(MarketStats::class, ['symbol' => 'NONE-USD'])
        ->assertViewHas('stats', []);
});
