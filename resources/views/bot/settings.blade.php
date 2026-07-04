@extends('layouts.app')

@section('title', 'Settings — ApexTradeAI')

@section('content')
    <h1>Bot Settings</h1>
    <p class="muted">Leave a field blank to use the .env default shown as its placeholder. Changes apply on the next <code>trading:run</code> cycle — no restart needed.</p>

    @if (session('status'))
        <div class="success-box">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="error-list">
            <ul style="margin:0; padding-left:1.2rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="warn-box">
        Kill switches (floor balance, max trades) are sticky — changing them here does not un-stop a bot that has already hit one. Reset requires clearing its row in <code>bot_states</code>.
    </div>

    <form class="settings" method="POST" action="{{ route('bot.settings.update') }}">
        @csrf

        @php
            $fields = [
                'symbol' => ['label' => 'Symbol', 'hint' => 'One spot pair, e.g. BTCUSDT.'],
                'interval' => ['label' => 'Candle interval', 'hint' => 'e.g. 15m, 1h. Must be a valid Binance interval.'],
                'ma_short' => ['label' => 'Short MA period', 'hint' => 'Number of candles.'],
                'ma_long' => ['label' => 'Long MA period', 'hint' => 'Must be greater than the short period.'],
                'take_profit_pct' => ['label' => 'Take-profit', 'hint' => 'Fraction, e.g. 0.10 = 10%.'],
                'stop_loss_pct' => ['label' => 'Stop-loss', 'hint' => 'Fraction, e.g. 0.15 = 15%.'],
                'reserve_ratio' => ['label' => 'Reserve ratio', 'hint' => 'Share of capital locked at bootstrap, e.g. 0.5 = 50%.'],
                'floor_balance' => ['label' => 'Floor balance (kill switch)', 'hint' => 'Bot stops permanently at or below this total.'],
                'max_trades' => ['label' => 'Max trades (kill switch)', 'hint' => 'Bot stops permanently after this many closed trades.'],
                'min_notional' => ['label' => 'Minimum order size', 'hint' => 'Smallest stake Binance will accept for this pair.'],
            ];
        @endphp

        @foreach ($fields as $key => $meta)
            <div class="field">
                <label for="{{ $key }}">
                    {{ $meta['label'] }}
                    @if (in_array($key, $overridden))
                        <span class="override-badge">overridden</span>
                    @endif
                </label>
                <input
                    type="text"
                    id="{{ $key }}"
                    name="{{ $key }}"
                    value="{{ old($key, $overridden && in_array($key, $overridden) ? $effective[$key] : '') }}"
                    placeholder="{{ $defaults[$key] }}"
                >
                <div class="hint">{{ $meta['hint'] }} Default: {{ $defaults[$key] }}</div>
            </div>
        @endforeach

        <div class="actions">
            <button type="submit">Save settings</button>
            <a href="{{ route('bot.status') }}" class="btn btn-secondary">Back to dashboard</a>
        </div>
    </form>
@endsection
