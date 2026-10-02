<?php

namespace App\Console\Commands;

use App\Actions\HandleMarketUpdateAction;
use App\Models\Market;
use App\Services\FigureMarkets\Backoff;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use JsonException;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Loop;
use Throwable;

use function Ratchet\Client\connect;

#[Signature('market:listen')]
#[Description('Keep the markets table current from the Figure Markets WebSocket feed and broadcast each update')]
class ListenToMarketsCommand extends Command
{
    /**
     * The provider closes idle connections after 30 seconds without a ping frame.
     */
    private const int PING_INTERVAL_SECONDS = 20;

    private bool $stopped = false;

    /**
     * True at start and after a connection that delivered messages, so failed connect retries do not
     * hit the REST API on every attempt.
     */
    private bool $shouldSync = true;

    private ?WebSocket $connection = null;

    public function handle(HandleMarketUpdateAction $handleUpdate, Backoff $backoff): int
    {
        $this->trap([SIGTERM, SIGINT], function (): void {
            $this->stopped = true;
            $this->connection?->close();
        });

        while (! $this->stopped) {
            if ($this->shouldSync) {
                $this->call('market:sync');
                $this->shouldSync = false;
            }

            $symbols = Market::open()->pluck('symbol');

            if ($symbols->isEmpty()) {
                $this->warn('No open markets to subscribe to.');
            } else {
                $this->listen($symbols, $handleUpdate, $backoff);
            }

            if ($this->stopped) {
                break;
            }

            $delay = $backoff->next();
            $this->line("Reconnecting in {$delay}s.");
            Sleep::for($delay)->seconds();
        }

        $this->info('Listener stopped.');

        return self::SUCCESS;
    }

    /**
     * Connects, subscribes and blocks until the connection closes or the loop throws.
     *
     * @param  Collection<int, string>  $symbols
     */
    private function listen(Collection $symbols, HandleMarketUpdateAction $handleUpdate, Backoff $backoff): void
    {
        try {
            connect(config('services.figure_markets.ws_url'))
                ->then(function (WebSocket $connection) use ($symbols, $handleUpdate, $backoff): void {
                    if ($this->stopped) {
                        $connection->close();

                        return;
                    }

                    $this->connection = $connection;

                    $ping = Loop::addPeriodicTimer(
                        self::PING_INTERVAL_SECONDS,
                        fn () => $connection->send(new Frame('', true, Frame::OP_PING)),
                    );

                    $connection->on('close', function (?int $code, ?string $reason) use ($ping): void {
                        Loop::cancelTimer($ping);
                        $this->connection = null;
                        $this->warn("Disconnected: {$code} {$reason}");
                        Log::warning('Provider WebSocket closed', ['code' => $code, 'reason' => $reason]);
                    });

                    $connection->on('message', function (MessageInterface $message) use ($handleUpdate, $backoff): void {
                        // A delivered message proves the provider accepted the connection, not just the handshake.
                        $backoff->reset();
                        $this->shouldSync = true;
                        $this->handleMessage((string) $message, $handleUpdate);
                    });

                    $symbols->each(fn (string $symbol) => $connection->send($this->subscribeMessage($symbol)));
                    $this->info("Connected, subscribed to {$symbols->count()} markets.");
                })
                ->catch(function (Throwable $e): void {
                    report($e);
                    $this->error("Connection failed: {$e->getMessage()}");
                    $this->connection?->close();
                });

            Loop::run();
        } catch (Throwable $e) {
            report($e);
            $this->error("Listener error: {$e->getMessage()}");
            $this->connection?->close();
            $this->connection = null;
        }
    }

    private function handleMessage(string $raw, HandleMarketUpdateAction $handleUpdate): void
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $decoded = null;
        }

        if (! is_array($decoded)) {
            Log::warning('Skipped a message that is not a JSON object', ['raw' => $raw]);
            $this->line('Skipped a message, see the log for details.');

            return;
        }

        $market = $handleUpdate->handle($decoded);

        if ($market === null) {
            $this->line('Skipped a message, see the log for details.');

            return;
        }

        $this->line("{$market->symbol} {$market->last_price} at {$market->price_updated_at->format('H:i:s.v')}");
    }

    private function subscribeMessage(string $symbol): string
    {
        return json_encode([
            'action' => 'SUBSCRIBE',
            'channel' => 'MARKET',
            'channelUuid' => (string) Str::uuid(),
            'symbol' => $symbol,
        ], JSON_THROW_ON_ERROR);
    }
}
