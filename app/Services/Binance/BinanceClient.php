<?php

namespace App\Services\Binance;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Binance Spot REST client, defaults to the Spot Testnet base URL.
 *
 * Only implements the handful of endpoints the trading bot needs. Deliberately
 * does not support futures/margin endpoints.
 */
class BinanceClient
{
    private string $baseUrl;

    private ?string $apiKey;

    private ?string $apiSecret;

    public function __construct(?string $baseUrl = null, ?string $apiKey = null, ?string $apiSecret = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('binance.base_url'), '/');
        $this->apiKey = $apiKey ?? config('binance.api_key');
        $this->apiSecret = $apiSecret ?? config('binance.api_secret');
    }

    /**
     * @return array<int, array<int, mixed>> raw kline arrays as returned by Binance
     */
    public function klines(string $symbol, string $interval, int $limit): array
    {
        $response = $this->request(
            fn () => Http::baseUrl($this->baseUrl)->timeout(15)->get('/api/v3/klines', [
                'symbol' => $symbol,
                'interval' => $interval,
                'limit' => $limit,
            ]),
            'klines'
        );

        $this->throwIfFailed($response, 'klines');

        return $response->json();
    }

    public function currentPrice(string $symbol): float
    {
        $response = $this->request(
            fn () => Http::baseUrl($this->baseUrl)->timeout(15)->get('/api/v3/ticker/price', ['symbol' => $symbol]),
            'ticker/price'
        );

        $this->throwIfFailed($response, 'ticker/price');

        return (float) $response->json('price');
    }

    /**
     * Market buy spending an exact amount of quote currency (e.g. USDT).
     */
    public function marketBuyByQuoteAmount(string $symbol, float $quoteOrderQty): array
    {
        return $this->signedOrder([
            'symbol' => $symbol,
            'side' => 'BUY',
            'type' => 'MARKET',
            'quoteOrderQty' => $this->trim($quoteOrderQty),
        ]);
    }

    /**
     * Market sell an exact base-asset quantity (e.g. BTC amount held).
     */
    public function marketSellByQuantity(string $symbol, float $quantity): array
    {
        return $this->signedOrder([
            'symbol' => $symbol,
            'side' => 'SELL',
            'type' => 'MARKET',
            'quantity' => $this->trim($quantity),
        ]);
    }

    private function signedOrder(array $params): array
    {
        $this->assertCredentials();

        $params['timestamp'] = (int) round(microtime(true) * 1000);
        $params['recvWindow'] = 5000;

        $query = http_build_query($params, '', '&');
        $signature = hash_hmac('sha256', $query, $this->apiSecret);
        $query .= '&signature='.$signature;

        // Params are already signed into the query string above. Force an empty
        // form body (instead of Laravel's default "[]" JSON body) so Binance's
        // strict parameter parser doesn't count a phantom 8th parameter.
        $response = $this->request(
            fn () => Http::withHeaders(['X-MBX-APIKEY' => $this->apiKey])
                ->timeout(15)
                ->asForm()
                ->post("{$this->baseUrl}/api/v3/order?{$query}", []),
            'order'
        );

        $this->throwIfFailed($response, 'order');

        return $response->json();
    }

    /**
     * Runs an HTTP call and converts network-level failures (timeouts, DNS
     * errors, connection refused, etc.) into a BinanceClientException so
     * every call site can rely on a single, consistent exception type —
     * rather than only handling non-2xx responses and letting transient
     * connectivity blips crash the caller uncaught.
     *
     * @param  \Closure(): Response  $call
     */
    private function request(\Closure $call, string $context): Response
    {
        try {
            return $call();
        } catch (ConnectionException $e) {
            Log::channel(config('logging.default'))->warning("Binance API [{$context}] connection failed", [
                'error' => $e->getMessage(),
            ]);

            throw new BinanceClientException("Binance API [{$context}] connection failed: {$e->getMessage()}", previous: $e);
        }
    }

    private function assertCredentials(): void
    {
        if (! $this->apiKey || ! $this->apiSecret) {
            throw new BinanceClientException(
                'Missing Binance API credentials. Set BINANCE_API_KEY and BINANCE_API_SECRET in .env (testnet keys from https://testnet.binance.vision).'
            );
        }
    }

    private function throwIfFailed(\Illuminate\Http\Client\Response $response, string $context): void
    {
        if ($response->failed()) {
            Log::channel(config('logging.default'))->error("Binance API [{$context}] failed", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new BinanceClientException("Binance API [{$context}] failed: {$response->body()}");
        }
    }

    private function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
    }
}
