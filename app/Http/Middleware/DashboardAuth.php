<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Simple HTTP Basic Auth gate for the dashboard/settings pages. This is a
 * single-user personal tool, not a multi-tenant app, so a full auth system
 * (Breeze/Fortify, users table, login forms) would be overkill. If
 * DASHBOARD_PASSWORD is not set in .env, the dashboard is left open —
 * fine for local-only use, but set it before exposing this beyond
 * 127.0.0.1.
 */
class DashboardAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $password = config('binance.dashboard_password');

        if (! $password) {
            return $next($request);
        }

        $provided = $request->getPassword();

        if ($request->getUser() !== 'admin' || $provided !== $password) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="ApexTradeAI"']);
        }

        return $next($request);
    }
}
