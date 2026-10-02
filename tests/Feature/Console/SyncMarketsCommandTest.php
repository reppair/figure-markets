<?php

use App\Actions\SyncMarketsAction;
use Illuminate\Http\Client\ConnectionException;

it('prints the synced count', function () {
    $this->mock(SyncMarketsAction::class)->shouldReceive('handle')->once()->andReturn(16);

    $this->artisan('market:sync')
        ->expectsOutputToContain('Synced 16 markets.')
        ->assertSuccessful();
});

it('reports a failed sync and exits non-zero', function () {
    $this->mock(SyncMarketsAction::class)->shouldReceive('handle')->once()->andThrow(new ConnectionException('timeout'));

    $this->artisan('market:sync')
        ->expectsOutputToContain('Market sync failed: timeout')
        ->assertFailed();
});
