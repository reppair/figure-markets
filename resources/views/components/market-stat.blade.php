@props(['label', 'value'])

<div {{ $attributes->class('flex flex-col gap-1') }}>
    <flux:text size="sm">{{ $label }}</flux:text>
    <flux:heading size="lg" class="tabular-nums">{{ $value }}</flux:heading>
</div>
