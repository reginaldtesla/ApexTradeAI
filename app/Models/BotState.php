<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotState extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_STOPPED_FLOOR = 'stopped_floor';

    public const STATUS_STOPPED_MAX_TRADES = 'stopped_max_trades';

    public const STATUS_STOPPED_MIN_NOTIONAL = 'stopped_min_notional';

    public const STATUS_STOPPED_MANUAL = 'stopped_manual';

    protected $fillable = [
        'symbol',
        'active_balance',
        'reserve_balance',
        'in_position',
        'position_qty',
        'position_entry_price',
        'position_stake',
        'total_trades',
        'status',
        'last_run_at',
    ];

    protected $casts = [
        'active_balance' => 'decimal:8',
        'reserve_balance' => 'decimal:8',
        'in_position' => 'boolean',
        'position_qty' => 'decimal:8',
        'position_entry_price' => 'decimal:8',
        'position_stake' => 'decimal:8',
        'last_run_at' => 'datetime',
    ];

    public function trades(): HasMany
    {
        return $this->hasMany(BotTrade::class);
    }

    public function totalBalance(): float
    {
        return (float) $this->active_balance + (float) $this->reserve_balance;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
