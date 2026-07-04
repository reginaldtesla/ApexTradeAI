<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_state_id')->constrained()->cascadeOnDelete();
            $table->string('symbol');

            $table->decimal('stake', 18, 8);
            $table->decimal('qty', 18, 8);
            $table->decimal('entry_price', 18, 8);
            $table->decimal('exit_price', 18, 8)->nullable();
            $table->decimal('proceeds', 18, 8)->nullable();
            $table->decimal('pnl', 18, 8)->nullable();

            // win | loss, set once the trade is closed
            $table->string('result')->nullable();
            $table->string('close_reason')->nullable();

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_trades');
    }
};
