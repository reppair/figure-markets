<?php

namespace App\Console\Commands;

use App\Actions\SyncMarketsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('market:sync')]
#[Description('Fetch the market list from Figure Markets and update the markets table')]
class SyncMarketsCommand extends Command
{
    public function handle(SyncMarketsAction $syncMarkets): int
    {
        try {
            $count = $syncMarkets->handle();
        } catch (Throwable $e) {
            report($e);
            $this->error('Market sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Synced {$count} markets.");

        return self::SUCCESS;
    }
}
