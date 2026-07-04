<?php

namespace App\Providers;

use App\Models\BotSettings;
use App\Services\Trading\MoneyManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MoneyManager::class, function () {
            $settings = BotSettings::effective();

            return new MoneyManager(
                reserveRatio: (float) $settings['reserve_ratio'],
                floorBalance: (float) $settings['floor_balance'],
                maxTrades: (int) $settings['max_trades'],
                minNotional: (float) $settings['min_notional'],
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
