<?php

use App\Services\FigureMarkets\RestClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
});

it('returns the market records of one page', function () {
    Http::fake(['*' => Http::response(jsonFixture('figure-markets/markets.json'))]);

    $records = app(RestClient::class)->markets();

    expect($records)->toHaveCount(16)
        ->and(collect($records)->pluck('symbol'))->toContain('HASH-USD');

    Http::assertSent(fn (Request $request) => $request->url() === config('services.figure_markets.rest_url').'/markets?size=50');
});

it('throws after three failed attempts', function () {
    Http::fake(['*' => Http::response(null, 500)]);

    expect(fn () => app(RestClient::class)->markets())->toThrow(RequestException::class);

    Http::assertSentCount(3);
});

it('throws when the provider cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

    expect(fn () => app(RestClient::class)->markets())->toThrow(ConnectionException::class);
});
