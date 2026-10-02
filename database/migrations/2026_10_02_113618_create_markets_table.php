<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique();
            $table->string('display_name');
            $table->string('base_asset');
            $table->string('quote_asset');
            $table->string('market_type');
            $table->string('status');
            $table->unsignedSmallInteger('price_precision');
            $table->decimal('last_price', total: 36, places: 18)->nullable()->comment('Null when the provider sent nothing; an untraded market has 0');
            $table->decimal('best_bid', total: 36, places: 18)->nullable()->comment('Null when the market has no order book');
            $table->decimal('best_ask', total: 36, places: 18)->nullable()->comment('Null when the market has no order book');
            $table->decimal('price_change_24h', total: 36, places: 18)->nullable()->comment('Null when the provider sent nothing');
            $table->decimal('percentage_change_24h', total: 12, places: 6)->nullable()->comment('Null when the provider sent nothing');
            $table->decimal('high_24h', total: 36, places: 18)->nullable()->comment('Null when the provider sent nothing');
            $table->decimal('low_24h', total: 36, places: 18)->nullable()->comment('Null when the provider sent nothing');
            $table->decimal('volume_24h', total: 36, places: 18)->nullable()->comment('Null when the provider sent nothing');
            $table->unsignedInteger('trade_count_24h')->nullable()->comment('Null when the provider sent nothing');
            $table->timestamp('price_updated_at', precision: 6)->nullable()->comment('Set only from WebSocket publishTime, never from REST');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('markets');
    }
};
