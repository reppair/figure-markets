<?php

use App\Services\FigureMarkets\Backoff;

it('doubles from one second to the cap with jitter', function () {
    $backoff = new Backoff;

    foreach ([1, 2, 4, 8, 16, 30, 30, 30] as $expected) {
        expect($backoff->next())->toBeBetween($expected * 0.8, $expected * 1.2);
    }
});

it('restarts at one second after a reset', function () {
    $backoff = new Backoff;
    $backoff->next();
    $backoff->next();

    $backoff->reset();

    expect($backoff->next())->toBeBetween(0.8, 1.2);
});
