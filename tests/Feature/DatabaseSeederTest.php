<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('seeds the demo user once across repeated runs', function () {
    $this->seed();
    $this->seed();

    expect(User::query()->where('email', 'demo@example.com')->count())->toBe(1);
});

it('seeds a demo user who reaches the dashboard', function () {
    $this->seed();

    $this->actingAs(User::query()->where('email', 'demo@example.com')->sole())
        ->get(route('dashboard'))
        ->assertOk();
});

it('seeds a demo user that can log in with the documented password', function () {
    $this->seed();

    expect(Auth::attempt(['email' => 'demo@example.com', 'password' => 'password']))->toBeTrue();
});
