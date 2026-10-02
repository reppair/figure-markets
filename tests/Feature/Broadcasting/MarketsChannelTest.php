<?php

use App\Models\User;

/*
 * Channels register on the driver that is default at boot (null in tests),
 * so re-register them on reverb, the driver that enforces authorization.
 */
beforeEach(function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
    config()->set('broadcasting.connections.reverb.app_id', 'test-app');

    require base_path('routes/channels.php');
});

it('authorizes a logged-in user on a market channel', function () {
    $this->actingAs(User::factory()->create())
        ->post('/broadcasting/auth', ['channel_name' => 'private-markets.7', 'socket_id' => '1234.5678'])
        ->assertOk();
});

it('rejects a guest from a market channel', function () {
    $this->post('/broadcasting/auth', ['channel_name' => 'private-markets.7', 'socket_id' => '1234.5678'])
        ->assertForbidden();
});
