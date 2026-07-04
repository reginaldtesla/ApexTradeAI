<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row table. Every column is nullable — null means "use the
     * .env/config default"; a non-null value overrides it. This lets the
     * settings page change bot rules without editing .env or restarting
     * anything (the CLI command reads fresh on every cycle).
     */
    public function up(): void
    {
        Schema::create('bot_settings', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->nullable();
            $table->string('interval')->nullable();
            $table->unsignedInteger('ma_short')->nullable();
            $table->unsignedInteger('ma_long')->nullable();
            $table->decimal('take_profit_pct', 6, 4)->nullable();
            $table->decimal('stop_loss_pct', 6, 4)->nullable();
            $table->decimal('reserve_ratio', 6, 4)->nullable();
            $table->decimal('floor_balance', 18, 8)->nullable();
            $table->unsignedInteger('max_trades')->nullable();
            $table->decimal('min_notional', 18, 8)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_settings');
    }
};
