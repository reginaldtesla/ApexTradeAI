<?php

return [

    // Binance Spot Testnet by default. Never point this at the live base URL
    // until you have watched the bot run correctly on testnet for a long time.
    'base_url' => env('BINANCE_BASE_URL', 'https://testnet.binance.vision'),

    'api_key' => env('BINANCE_API_KEY'),
    'api_secret' => env('BINANCE_API_SECRET'),

    // Trading pair the bot is allowed to touch. Keep this to a single,
    // liquid spot pair.
    'symbol' => env('BOT_SYMBOL', 'BTCUSDT'),

    // Candle interval used for the moving-average signal.
    'interval' => env('BOT_INTERVAL', '15m'),

    // Moving average crossover periods.
    'ma_short' => (int) env('BOT_MA_SHORT', 9),
    'ma_long' => (int) env('BOT_MA_LONG', 21),

    // Exit rules, as a fraction (0.10 = 10%).
    'take_profit_pct' => (float) env('BOT_TAKE_PROFIT_PCT', 0.10),
    'stop_loss_pct' => (float) env('BOT_STOP_LOSS_PCT', 0.15),

    // Money management (see App\Services\Trading\MoneyManager).
    'initial_capital' => (float) env('BOT_INITIAL_CAPITAL', 10.0),
    'reserve_ratio' => (float) env('BOT_RESERVE_RATIO', 0.5),

    // Hard kill switches. The bot stops trading permanently once either is hit.
    'floor_balance' => (float) env('BOT_FLOOR_BALANCE', 8.0),
    'max_trades' => (int) env('BOT_MAX_TRADES', 20),

    // Smallest USDT notional Binance will accept for the pair. If the active
    // stake falls below this, the bot cannot open new trades.
    'min_notional' => (float) env('BOT_MIN_NOTIONAL', 5.0),

    // HTTP Basic Auth password (username is always "admin") for the
    // dashboard/settings pages. Leave blank for local-only use; set before
    // exposing this app beyond 127.0.0.1.
    'dashboard_password' => env('DASHBOARD_PASSWORD'),
];
