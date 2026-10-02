<div class="flex flex-col gap-6">
    @if ($symbols->isEmpty())
        <flux:callout icon="information-circle" heading="No markets yet" data-lw-no-markets>
            Start <code>composer run dev</code>: the listener loads the market list and streams live prices. Then reload this page.
            <code>php artisan market:sync</code> alone fills the list without live updates.
        </flux:callout>
    @else
        <div class="max-w-xs">
            <flux:select wire:model.live="symbol" label="Market">
                @foreach ($symbols as $option)
                    <flux:select.option :value="$option" wire:key="option-{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="max-w-3xl">
            <livewire:market-stats :symbol="$symbol" :wire:key="$symbol" />
        </div>
    @endif
</div>
