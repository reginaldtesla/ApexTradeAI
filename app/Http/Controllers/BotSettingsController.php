<?php

namespace App\Http\Controllers;

use App\Models\BotSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BotSettingsController extends Controller
{
    private const VALID_INTERVALS = [
        '1m', '3m', '5m', '15m', '30m', '1h', '2h', '4h', '6h', '8h', '12h', '1d', '3d', '1w', '1M',
    ];

    public function index()
    {
        return view('bot.settings', [
            'effective' => BotSettings::effective(),
            'overridden' => BotSettings::overriddenKeys(),
            'defaults' => config('binance'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Empty form fields mean "revert to default" — normalise "" to null
        // before validation so `nullable` rules actually skip them.
        $request->merge(collect($request->only(BotSettings::KEYS))
            ->map(fn ($value) => $value === '' ? null : $value)
            ->toArray());

        $validated = $request->validate([
            'symbol' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'interval' => ['nullable', 'string', 'in:'.implode(',', self::VALID_INTERVALS)],
            'ma_short' => ['nullable', 'integer', 'min:2', 'max:200'],
            'ma_long' => ['nullable', 'integer', 'min:3', 'max:400'],
            'take_profit_pct' => ['nullable', 'numeric', 'min:0.001', 'max:1'],
            'stop_loss_pct' => ['nullable', 'numeric', 'min:0.001', 'max:1'],
            'reserve_ratio' => ['nullable', 'numeric', 'min:0', 'max:0.95'],
            'floor_balance' => ['nullable', 'numeric', 'min:0'],
            'max_trades' => ['nullable', 'integer', 'min:1'],
            'min_notional' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! empty($validated['symbol'])) {
            $validated['symbol'] = strtoupper($validated['symbol']);
        }

        $effective = BotSettings::effective();
        $resolvedShort = $validated['ma_short'] ?? $effective['ma_short'];
        $resolvedLong = $validated['ma_long'] ?? $effective['ma_long'];
        if ($resolvedLong <= $resolvedShort) {
            return redirect()->route('bot.settings')
                ->withErrors(['ma_long' => 'The long MA period must be greater than the short MA period.'])
                ->withInput();
        }

        // Blank fields arrive as null already thanks to the `nullable` rules
        // combined with the form sending empty strings; normalise explicitly
        // so "clear the field" reliably reverts to the .env default.
        $overrides = [];
        foreach (BotSettings::KEYS as $key) {
            $overrides[$key] = $validated[$key] ?? null;
        }

        BotSettings::current()->update($overrides);

        return redirect()->route('bot.settings')->with('status', 'Settings saved.');
    }
}
