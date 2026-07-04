@extends('layouts.app')

@section('title', 'Dashboard — ApexTradeAI')

@section('content')
    <h1>Trading Bot Status</h1>
    <p class="muted">Symbol: <span id="stat-symbol">{{ $settings['symbol'] }}</span> · Testnet base URL: {{ config('binance.base_url') }}</p>

    <div class="warn-box">
        Paper/testnet money only unless you have deliberately switched BINANCE_BASE_URL to the live API. This page is read-only — control the rules from <a href="{{ route('bot.settings') }}">Settings</a>.
    </div>

    @if (! $state)
        <p class="empty">Bot has not run yet. Run <code>php artisan trading:run</code> once to initialise it.</p>
    @else
        <div class="live-header">
            <h2 style="margin: 0;"><span class="live-dot"></span> Live market view</h2>
            <span class="muted" id="live-updated">updating…</span>
        </div>

        <div class="cards">
            <div class="card">
                <div class="label">Price</div>
                <div class="value" id="live-price">—</div>
            </div>
            <div class="card">
                <div class="label">MA {{ $settings['ma_short'] }}</div>
                <div class="value" id="live-ma-short">—</div>
            </div>
            <div class="card">
                <div class="label">MA {{ $settings['ma_long'] }}</div>
                <div class="value" id="live-ma-long">—</div>
            </div>
            <div class="card">
                <div class="label">Signal</div>
                <div class="value" id="live-signal">—</div>
            </div>
        </div>
        <p class="muted" id="live-error"></p>

        <div id="candle-chart" class="chart-box"></div>
        <p class="muted chart-legend">
            <span><span class="swatch" style="background:#60a5fa;"></span> MA {{ $settings['ma_short'] }}</span>
            <span><span class="swatch" style="background:#fbbf24;"></span> MA {{ $settings['ma_long'] }}</span>
        </p>

        <h2>Profit &amp; loss</h2>
        <div class="cards">
            <div class="card">
                <div class="label">Realized P&amp;L</div>
                <div class="value {{ $summary['realized_pnl'] > 0 ? 'win' : ($summary['realized_pnl'] < 0 ? 'loss' : '') }}" id="pnl-realized">
                    {{ ($summary['realized_pnl'] >= 0 ? '+' : '') . number_format($summary['realized_pnl'], 4) }}
                </div>
            </div>
            <div class="card">
                <div class="label">Return since start</div>
                <div class="value {{ ($summary['return_pct'] ?? 0) > 0 ? 'win' : (($summary['return_pct'] ?? 0) < 0 ? 'loss' : '') }}" id="pnl-return">
                    {{ $summary['return_pct'] !== null ? (($summary['return_pct'] >= 0 ? '+' : '') . number_format($summary['return_pct'], 2) . '%') : '—' }}
                </div>
            </div>
            <div class="card">
                <div class="label">Win / Loss</div>
                <div class="value" id="pnl-wl">{{ $summary['wins'] }}W / {{ $summary['losses'] }}L</div>
            </div>
            <div class="card">
                <div class="label">Win rate</div>
                <div class="value" id="pnl-winrate">{{ $summary['win_rate'] !== null ? number_format($summary['win_rate'], 1) . '%' : '—' }}</div>
            </div>
            <div class="card">
                <div class="label">Best trade</div>
                <div class="value win" id="pnl-best">{{ $summary['best_trade'] !== null ? '+' . number_format($summary['best_trade'], 4) : '—' }}</div>
            </div>
            <div class="card">
                <div class="label">Worst trade</div>
                <div class="value loss" id="pnl-worst">{{ $summary['worst_trade'] !== null ? number_format($summary['worst_trade'], 4) : '—' }}</div>
            </div>
        </div>

        <h2>Bot state</h2>
        <div class="cards">
            <div class="card">
                <div class="label">Status</div>
                <div class="value {{ $state->isActive() ? 'status-active' : 'status-stopped' }}" id="stat-status">
                    {{ str_replace('_', ' ', $state->status) }}
                </div>
            </div>
            <div class="card">
                <div class="label">Active (at risk)</div>
                <div class="value" id="stat-active">{{ number_format($state->active_balance, 4) }}</div>
            </div>
            <div class="card">
                <div class="label">Reserve (locked)</div>
                <div class="value" id="stat-reserve">{{ number_format($state->reserve_balance, 4) }}</div>
            </div>
            <div class="card">
                <div class="label">Total balance</div>
                <div class="value" id="stat-total">{{ number_format($state->totalBalance(), 4) }}</div>
            </div>
            <div class="card">
                <div class="label">Trades closed</div>
                <div class="value" id="stat-trades">{{ $state->total_trades }} / {{ $settings['max_trades'] }}</div>
            </div>
            <div class="card">
                <div class="label">Position</div>
                <div class="value" id="stat-position" style="font-size: 1rem;">{{ $state->in_position ? 'Yes — pnl n/a' : 'No' }}</div>
            </div>
            <div class="card">
                <div class="label">Last run</div>
                <div class="value" id="stat-last-run" style="font-size: 1rem;">{{ $state->last_run_at?->diffForHumans() ?? 'never' }}</div>
            </div>
        </div>

        <h2>Recent trades</h2>
        <div id="trades-wrap">
            @if ($trades->isEmpty())
                <p class="empty" id="trades-empty">No trades yet.</p>
            @endif
            <table id="trades-table" style="{{ $trades->isEmpty() ? 'display:none;' : '' }}">
                <thead>
                    <tr>
                        <th>Opened</th>
                        <th>Stake</th>
                        <th>Entry</th>
                        <th>Exit</th>
                        <th>PnL</th>
                        <th>Result</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody id="trades-tbody">
                    @foreach ($trades as $trade)
                        <tr>
                            <td>{{ $trade->opened_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ number_format($trade->stake, 4) }}</td>
                            <td>{{ number_format($trade->entry_price, 4) }}</td>
                            <td>{{ $trade->exit_price ? number_format($trade->exit_price, 4) : '—' }}</td>
                            <td class="{{ $trade->pnl > 0 ? 'win' : ($trade->pnl < 0 ? 'loss' : '') }}">
                                {{ $trade->pnl !== null ? number_format($trade->pnl, 4) : '—' }}
                            </td>
                            <td>{{ $trade->result ?? 'open' }}</td>
                            <td>{{ $trade->close_reason ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <h2>Activity log</h2>
        <pre id="log-tail" class="log-box">waiting for activity…</pre>
    @endif
@endsection

@if ($state ?? null)
    @push('scripts')
    <script src="https://unpkg.com/lightweight-charts@5.2.0/dist/lightweight-charts.standalone.production.js"></script>
    <script>
    (function () {
        const liveUrl = @json(route('bot.live'));
        const candlesUrl = @json(route('bot.candles'));

        let chart = null;
        let candleSeries = null;
        let maShortSeries = null;
        let maLongSeries = null;

        function initChart() {
            if (typeof LightweightCharts === 'undefined') return false;
            const container = document.getElementById('candle-chart');
            if (!container) return false;

            chart = LightweightCharts.createChart(container, {
                autoSize: true,
                layout: { background: { color: '#0b0d12' }, textColor: '#9ca3af' },
                grid: {
                    vertLines: { color: '#1a1f29' },
                    horzLines: { color: '#1a1f29' },
                },
                timeScale: { timeVisible: true, secondsVisible: false, borderColor: '#262b36' },
                rightPriceScale: { borderColor: '#262b36' },
                crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
            });

            candleSeries = chart.addSeries(LightweightCharts.CandlestickSeries, {
                upColor: '#34d399',
                downColor: '#f87171',
                borderVisible: false,
                wickUpColor: '#34d399',
                wickDownColor: '#f87171',
            });
            maShortSeries = chart.addSeries(LightweightCharts.LineSeries, { color: '#60a5fa', lineWidth: 2, priceLineVisible: false, lastValueVisible: false });
            maLongSeries = chart.addSeries(LightweightCharts.LineSeries, { color: '#fbbf24', lineWidth: 2, priceLineVisible: false, lastValueVisible: false });

            return true;
        }

        async function pollCandles() {
            if (!chart && !initChart()) return;
            try {
                const res = await fetch(candlesUrl, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                if (data.error) return;

                candleSeries.setData(data.candles || []);
                maShortSeries.setData(data.ma_short || []);
                maLongSeries.setData(data.ma_long || []);
            } catch (e) {
                // Chart just stays on last known data; the main poll() below
                // already surfaces connectivity issues via live-updated text.
            }
        }

        function fmt(n, d) {
            if (n === null || n === undefined) return '—';
            return Number(n).toLocaleString(undefined, { minimumFractionDigits: d, maximumFractionDigits: d });
        }

        function signalLabel(s) {
            if (s === 'bullish') return '▲ Bullish';
            if (s === 'bearish') return '▼ Bearish';
            if (s === 'neutral') return '– Neutral';
            return '—';
        }

        function signalClass(s) {
            if (s === 'bullish') return 'win';
            if (s === 'bearish') return 'loss';
            return '';
        }

        function signClass(n) {
            return n > 0 ? 'win' : (n < 0 ? 'loss' : '');
        }

        function renderSummary(s) {
            if (!s) return;
            const realized = document.getElementById('pnl-realized');
            realized.textContent = (s.realized_pnl >= 0 ? '+' : '') + fmt(s.realized_pnl, 4);
            realized.className = 'value ' + signClass(s.realized_pnl);

            const ret = document.getElementById('pnl-return');
            ret.textContent = s.return_pct !== null ? ((s.return_pct >= 0 ? '+' : '') + s.return_pct.toFixed(2) + '%') : '—';
            ret.className = 'value ' + signClass(s.return_pct ?? 0);

            document.getElementById('pnl-wl').textContent = s.wins + 'W / ' + s.losses + 'L';
            document.getElementById('pnl-winrate').textContent = s.win_rate !== null ? s.win_rate.toFixed(1) + '%' : '—';
            document.getElementById('pnl-best').textContent = s.best_trade !== null ? ('+' + fmt(s.best_trade, 4)) : '—';
            document.getElementById('pnl-worst').textContent = s.worst_trade !== null ? fmt(s.worst_trade, 4) : '—';
        }

        function renderTrades(trades) {
            const tbody = document.getElementById('trades-tbody');
            const table = document.getElementById('trades-table');
            const empty = document.getElementById('trades-empty');
            if (!trades.length) {
                table.style.display = 'none';
                if (empty) empty.style.display = 'block';
                return;
            }
            if (empty) empty.style.display = 'none';
            table.style.display = '';
            tbody.innerHTML = trades.map(function (t) {
                const pnlClass = t.pnl > 0 ? 'win' : (t.pnl < 0 ? 'loss' : '');
                return '<tr>' +
                    '<td>' + (t.opened_at ?? '—') + '</td>' +
                    '<td>' + fmt(t.stake, 4) + '</td>' +
                    '<td>' + fmt(t.entry_price, 4) + '</td>' +
                    '<td>' + (t.exit_price !== null ? fmt(t.exit_price, 4) : '—') + '</td>' +
                    '<td class="' + pnlClass + '">' + (t.pnl !== null ? fmt(t.pnl, 4) : '—') + '</td>' +
                    '<td>' + (t.result ?? 'open') + '</td>' +
                    '<td>' + (t.close_reason ?? '—') + '</td>' +
                    '</tr>';
            }).join('');
        }

        async function poll() {
            try {
                const res = await fetch(liveUrl, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();

                document.getElementById('live-price').textContent = fmt(data.market.price, 6);
                document.getElementById('live-ma-short').textContent = fmt(data.market.ma_short, 6);
                document.getElementById('live-ma-long').textContent = fmt(data.market.ma_long, 6);
                const sigEl = document.getElementById('live-signal');
                sigEl.textContent = signalLabel(data.market.signal);
                sigEl.className = 'value ' + signalClass(data.market.signal);
                document.getElementById('live-error').textContent = data.market.error ? ('API warning: ' + data.market.error) : '';

                if (data.state) {
                    const s = data.state;
                    const statusEl = document.getElementById('stat-status');
                    statusEl.textContent = s.status.replace(/_/g, ' ');
                    statusEl.className = 'value ' + (s.active ? 'status-active' : 'status-stopped');
                    document.getElementById('stat-active').textContent = fmt(s.active_balance, 4);
                    document.getElementById('stat-reserve').textContent = fmt(s.reserve_balance, 4);
                    document.getElementById('stat-total').textContent = fmt(s.total_balance, 4);
                    document.getElementById('stat-trades').textContent = s.total_trades + ' / ' + s.max_trades;
                    document.getElementById('stat-position').textContent = s.in_position
                        ? ('Yes — pnl ' + (s.pnl_pct !== null ? (s.pnl_pct * 100).toFixed(2) + '%' : 'n/a'))
                        : 'No';
                    document.getElementById('stat-last-run').textContent = s.last_run_human ?? 'never';
                }

                renderTrades(data.trades || []);
                renderSummary(data.summary);

                const logEl = document.getElementById('log-tail');
                logEl.textContent = (data.log_tail && data.log_tail.length) ? data.log_tail.join('\n') : 'no recent activity';
                logEl.scrollTop = logEl.scrollHeight;

                document.getElementById('live-updated').textContent = 'updated ' + new Date().toLocaleTimeString();
            } catch (e) {
                document.getElementById('live-updated').textContent = 'live update failed — retrying…';
            }
        }

        poll();
        setInterval(poll, 5000);
        pollCandles();
        setInterval(pollCandles, 15000);
    })();
    </script>
    @endpush
@endif
