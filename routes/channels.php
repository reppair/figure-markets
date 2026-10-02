<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Registering the callback is what makes the channels private: guests are
 * rejected before it runs, and any logged-in user may watch any market, so
 * there is no per-user rule. The key is the market id because symbols may
 * contain dots, which a channel parameter never matches.
 */
Broadcast::channel('markets.{id}', fn () => true);
