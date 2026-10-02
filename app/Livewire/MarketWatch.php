<?php

namespace App\Livewire;

use App\Models\Market;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MarketWatch extends Component
{
    private const string DEFAULT_SYMBOL = 'HASH-USDC';

    public ?string $symbol = null;

    public function render(): View
    {
        $symbols = Market::open()->orderBy('symbol')->pluck('symbol');

        if (! $symbols->contains($this->symbol)) {
            $this->symbol = $symbols->contains(self::DEFAULT_SYMBOL) ? self::DEFAULT_SYMBOL : $symbols->first();
        }

        return view('livewire.market-watch', ['symbols' => $symbols]);
    }
}
