{{--
    Livewire only stops listening when this card is replaced. Leaving the channel
    one tick later, after that cleanup, stops Reverb from pushing the old market
    and avoids the cleanup resubscribing to a channel that is already gone.
--}}
<div
    class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700"
    data-lw-market="{{ $symbol }}"
    @if ($this->market)
        x-data="{ destroy() { setTimeout(() => window.Echo.leave('markets.{{ $this->market->id }}')) } }"
    @endif
>
    <flux:heading size="xl">{{ $symbol }}</flux:heading>

    @if ($stats === [])
        <flux:text class="mt-2">This market is not available.</flux:text>
    @else
        <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
            @foreach ($stats as $label => $value)
                <x-market-stat :label="$label" :value="$value" />
            @endforeach
        </div>
    @endif
</div>
