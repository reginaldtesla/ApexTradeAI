<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ApexTradeAI')</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #0f1115; color: #e5e7eb; margin: 0; }
        a { color: #60a5fa; text-decoration: none; }
        a:hover { text-decoration: underline; }
        nav.topbar { background: #161a22; border-bottom: 1px solid #262b36; padding: 0.9rem 2rem; display: flex; align-items: center; justify-content: space-between; }
        nav.topbar .brand { font-weight: 700; font-size: 1.05rem; color: #e5e7eb; }
        nav.topbar .links a { margin-left: 1.25rem; font-size: 0.9rem; color: #9ca3af; }
        nav.topbar .links a.active, nav.topbar .links a:hover { color: #e5e7eb; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 2rem; }
        h1 { font-size: 1.5rem; margin-bottom: 0.25rem; }
        h2 { font-size: 1.1rem; }
        .muted { color: #9ca3af; font-size: 0.875rem; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin: 1.5rem 0; }
        .card { background: #161a22; border: 1px solid #262b36; border-radius: 10px; padding: 1rem; }
        .card .label { font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; }
        .card .value { font-size: 1.4rem; font-weight: 600; margin-top: 0.25rem; }
        .status-active { color: #34d399; }
        .status-stopped { color: #f87171; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.875rem; }
        th, td { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 1px solid #262b36; }
        th { color: #9ca3af; font-weight: 500; }
        .win { color: #34d399; }
        .loss { color: #f87171; }
        .empty { color: #9ca3af; padding: 2rem 0; text-align: center; }
        .warn-box { background: #2a1f14; border: 1px solid #5c4423; color: #fbbf24; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.875rem; }
        .success-box { background: #14261c; border: 1px solid #235c3d; color: #4ade80; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.875rem; }
        form.settings { background: #161a22; border: 1px solid #262b36; border-radius: 10px; padding: 1.5rem; margin-top: 1rem; }
        .field { margin-bottom: 1.1rem; }
        .field label { display: block; font-size: 0.85rem; color: #d1d5db; margin-bottom: 0.3rem; }
        .field .hint { font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem; }
        .field .override-badge { font-size: 0.7rem; color: #fbbf24; margin-left: 0.5rem; }
        .field input { width: 100%; max-width: 320px; background: #0f1115; border: 1px solid #333a48; color: #e5e7eb; border-radius: 6px; padding: 0.5rem 0.7rem; font-size: 0.9rem; }
        .field input:focus { outline: none; border-color: #60a5fa; }
        .actions { margin-top: 1.5rem; display: flex; gap: 0.75rem; }
        button, .btn { background: #2563eb; color: #fff; border: none; border-radius: 6px; padding: 0.6rem 1.1rem; font-size: 0.9rem; cursor: pointer; }
        button:hover, .btn:hover { background: #1d4ed8; text-decoration: none; }
        .btn-secondary { background: #262b36; }
        .btn-secondary:hover { background: #333a48; }
        .error-list { background: #2a1414; border: 1px solid #5c2323; color: #fca5a5; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; }
        .live-header { display: flex; align-items: center; justify-content: space-between; margin-top: 1.75rem; }
        .live-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #34d399; margin-right: 0.4rem; animation: pulse 1.6s ease-in-out infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.25; } }
        .log-box { background: #0b0d12; border: 1px solid #262b36; border-radius: 8px; padding: 0.85rem 1rem; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.78rem; line-height: 1.5; color: #9ca3af; max-height: 260px; overflow-y: auto; white-space: pre-wrap; word-break: break-word; }
        .chart-box { background: #0b0d12; border: 1px solid #262b36; border-radius: 10px; height: 340px; margin-top: 0.5rem; }
        .chart-legend { display: flex; gap: 1.25rem; margin-top: 0.6rem; }
        .chart-legend .swatch { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 0.4rem; vertical-align: middle; }
    </style>
</head>
<body>
    <nav class="topbar">
        <span class="brand">ApexTradeAI</span>
        <span class="links">
            <a href="{{ route('bot.status') }}" class="{{ request()->routeIs('bot.status') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('bot.settings') }}" class="{{ request()->routeIs('bot.settings') ? 'active' : '' }}">Settings</a>
        </span>
    </nav>
    <div class="wrap">
        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>
