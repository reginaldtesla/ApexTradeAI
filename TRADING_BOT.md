# ApexTradeAI — Spot Trading Bot (Testnet)

An automated Binance **spot** trading bot with a fixed money-management rule
and a simple moving-average crossover strategy. Built to run on
**Binance Spot Testnet** with fake funds first — do not point it at live
trading until you have watched it run for a long time and understand exactly
what it does.

## What it does

Every cycle (`php artisan trading:run`), the bot:

1. Fetches recent candles for one symbol (default `BTCUSDT`) and computes a
   short/long moving-average crossover signal.
2. If **flat** and the signal just turned bullish, it buys using the entire
   current `active_balance` (never the reserve).
3. If **in a position**, it checks, in order: take-profit %, stop-loss %,
   then a bearish crossover — whichever triggers first closes the position.
4. On close, it applies the money-management rule and re-checks the kill
   switches.

## Money management rule

- **Start:** capital splits into `active_balance` (at risk) and
  `reserve_balance` (locked, never directly re-risked). Default 50/50.
- **Every trade** risks exactly `active_balance`. The reserve is never spent
  directly.
- **On a win:** the next stake becomes 50% of the new total
  (`reserve + proceeds`); the other 50% moves into reserve.
- **On a loss:** reserve is untouched; whatever is left from the stake
  becomes the new `active_balance` (no top-up from reserve).

This is a **position-sizing rule**, not a guarantee of profit — a loss right
after a win still risks a larger stake than a loss after a loss. See the kill
switches below for the actual safety net.

## Kill switches (the bot disables itself)

Configured in `config/binance.php` / `.env`:

| Trigger | Config | Default |
|---|---|---|
| Total balance (active + reserve) falls to/below floor | `BOT_FLOOR_BALANCE` | 8 |
| Number of closed trades reaches the max | `BOT_MAX_TRADES` | 20 |
| Active balance drops below the minimum order size | `BOT_MIN_NOTIONAL` | 5 |

Once stopped, `trading:run` logs the status and does nothing until you
manually reset the row in `bot_states` (or delete it to start over).

## Setup

1. Create a **Binance Spot Testnet** account and API key at
   <https://testnet.binance.vision> (separate from your real Binance
   account — fake funds only).
2. Copy `.env.example` to `.env` if you haven't already, and fill in:

   ```
   BINANCE_BASE_URL=https://testnet.binance.vision
   BINANCE_API_KEY=your_testnet_key
   BINANCE_API_SECRET=your_testnet_secret
   ```

3. Adjust the bot's rules if you want (symbol, MA periods, take-profit/stop-loss,
   starting capital, floor, max trades) — see `.env.example` for all `BOT_*`
   variables.
4. Run migrations: `php artisan migrate`
5. Run one cycle manually to confirm it works: `php artisan trading:run`
6. Check status at `/` or `/bot` (e.g. `php artisan serve` then visit
   `http://127.0.0.1:8000/`).

## Web dashboard

- **`/`** and **`/bot`** — read-only dashboard: current status, active/reserve
  balances, kill-switch state, and recent trades.
- **`/settings`** — edit bot rules (symbol, interval, MA periods, TP/SL,
  reserve ratio, kill switches) from the browser. Overrides are stored in the
  `bot_settings` table; leave a field blank to fall back to the `.env`
  default shown as its placeholder. Changes apply on the **next** `trading:run`
  cycle — no restart needed. `initial_capital` is intentionally not editable
  here since it only matters the very first time the bot ever bootstraps its
  balances.
- Kill switches (floor balance, max trades, min notional) are **sticky** —
  changing the settings does not un-stop a bot that already hit one. To
  reset, clear or delete the row in `bot_states`.

### Protecting the dashboard

Set `DASHBOARD_PASSWORD` in `.env` to require HTTP Basic Auth (username
`admin`) on `/`, `/bot`, and `/settings`. Left blank, the dashboard is open —
fine for local-only use on `127.0.0.1`, but set it before exposing this
beyond your own machine.

## Running continuously

Laravel's scheduler has two entries registered in `routes/console.php`:

- `trading:run` — every 5 minutes (the actual trading cycle)
- `trading:summary` — hourly heartbeat (see "Alerts / monitoring" below)

Something still has to invoke the scheduler itself — there is no cron on this
Windows/Apache setup. This is already wired up for you:

- **`scripts/run-scheduler.bat`** runs `php artisan schedule:run` once and
  appends output to `storage/logs/scheduler.log`.
- A Windows Task Scheduler task named **`ApexTradeAI-Scheduler`** has been
  registered to run that script every minute. Check/manage it with:

  ```powershell
  schtasks /query /tn "ApexTradeAI-Scheduler" /v /fo LIST
  schtasks /end /tn "ApexTradeAI-Scheduler"      REM stop it
  schtasks /delete /tn "ApexTradeAI-Scheduler" /f  REM remove it entirely
  ```

If you ever move the project folder or PHP install, re-run the `schtasks
/create` command in the setup notes with the updated paths, or just delete
and recreate the task.

## Alerts / monitoring

Two things guard against "I set it and forgot about it and something broke
silently":

1. **`storage/logs/trading-bot-*.log`** — every meaningful event (position
   opened/closed, kill switch triggered, API errors, bot stopped) is logged
   here via the dedicated `trading` log channel (`config/logging.php`),
   separate from Laravel's general log noise.
2. **`trading:summary`** runs hourly and writes a one-line heartbeat. If the
   last `trading:run` cycle is more than 15 minutes old (3x the expected
   5-minute interval), it logs a warning that the scheduler itself may have
   stopped — the most common way an "unattended" bot silently does nothing.

Worth periodically tailing the trading log or checking `/bot` — automation
removes the need to click buttons, not the need to occasionally look.

## Going live (only after you're sure)

Switching `BINANCE_BASE_URL` to `https://api.binance.com` and using a real
API key makes this trade real money. Before doing that:

- Watch the bot run on testnet for weeks, not hours.
- Use a **trade-only** API key with **withdrawals disabled**.
- Start with an amount you have already decided you can lose completely.
- Re-read the kill switch values — they are your only protection against a
  bug wiping the account.

## Limitations (be aware)

- Strategy is a simple MA crossover — it is a starting point for learning,
  not a proven edge.
- No slippage/partial-fill modelling beyond what Binance actually reports.
- Single symbol, single position at a time, by design — this is meant to be
  small and easy to reason about, not a full trading platform.
