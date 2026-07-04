<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_states', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique();

            // Money the bot is currently allowed to risk on the next trade.
            $table->decimal('active_balance', 18, 8);

            // Money that has been locked away and will never be re-risked
            // directly (it only grows when a winning trade redistributes
            // half of the new total back into it).
            $table->decimal('reserve_balance', 18, 8);

            $table->boolean('in_position')->default(false);
            $table->decimal('position_qty', 18, 8)->nullable();
            $table->decimal('position_entry_price', 18, 8)->nullable();
            $table->decimal('position_stake', 18, 8)->nullable();

            $table->unsignedInteger('total_trades')->default(0);

            // active | stopped_floor | stopped_max_trades | stopped_min_notional | stopped_manual
            $table->string('status')->default('active');

            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_states');
    }
};
