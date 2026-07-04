<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotTrade extends Model
{
    protected $fillable = [
        'bot_state_id',
        'symbol',
        'stake',
        'qty',
        'entry_price',
        'exit_price',
        'proceeds',
        'pnl',
        'result',
        'close_reason',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'stake' => 'decimal:8',
        'qty' => 'decimal:8',
        'entry_price' => 'decimal:8',
        'exit_price' => 'decimal:8',
        'proceeds' => 'decimal:8',
        'pnl' => 'decimal:8',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function botState(): BelongsTo
    {
        return $this->belongsTo(BotState::class);
    }
}
