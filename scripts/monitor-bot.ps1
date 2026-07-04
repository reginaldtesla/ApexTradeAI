# Watches the trading bot's logs and health for problems.
# Prints "ALERT: ..." lines for anything that looks like an error, and a
# "HEARTBEAT: ..." status line every couple of minutes.

$root = Split-Path -Parent $PSScriptRoot
$tradingLogPattern = Join-Path $root "storage\logs\trading-bot-*.log"
$laravelLog = Join-Path $root "storage\logs\laravel.log"
$schedulerLog = Join-Path $root "storage\logs\scheduler.log"

function Get-LatestTradingLog {
    Get-ChildItem $tradingLogPattern -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
}

$positions = @{}

function Get-NewLines($path) {
    if (-not (Test-Path $path)) { return @() }
    $len = (Get-Item $path).Length
    if (-not $positions.ContainsKey($path)) {
        $positions[$path] = $len
        return @()
    }
    if ($len -lt $positions[$path]) {
        # File rotated/truncated
        $positions[$path] = 0
    }
    if ($len -eq $positions[$path]) { return @() }

    $stream = [System.IO.File]::Open($path, 'Open', 'Read', 'ReadWrite')
    $stream.Seek($positions[$path], 'Begin') | Out-Null
    $reader = New-Object System.IO.StreamReader($stream)
    $text = $reader.ReadToEnd()
    $reader.Close()
    $stream.Close()
    $positions[$path] = $len

    if ([string]::IsNullOrWhiteSpace($text)) { return @() }
    return $text -split "`r?`n" | Where-Object { $_ -ne '' }
}

Write-Output "Monitor started at $(Get-Date). Watching trading-bot log, laravel.log, scheduler.log."

$lastHeartbeat = Get-Date "1970-01-01"

while ($true) {
    $tradingLog = Get-LatestTradingLog
    if ($tradingLog) {
        foreach ($line in Get-NewLines $tradingLog.FullName) {
            if ($line -match 'ERROR|CRITICAL|Filter failure|failed|Kill switch') {
                Write-Output "ALERT (trading): $line"
            }
        }
    }

    foreach ($line in Get-NewLines $laravelLog) {
        if ($line -match '\.ERROR:|\.CRITICAL:|Exception') {
            Write-Output "ALERT (laravel): $line"
        }
    }

    foreach ($line in Get-NewLines $schedulerLog) {
        if ($line -match 'ERROR|Exception|failed') {
            Write-Output "ALERT (scheduler): $line"
        }
    }

    if (((Get-Date) - $lastHeartbeat).TotalSeconds -ge 120) {
        try {
            $resp = Invoke-RestMethod -Uri "http://127.0.0.1:8000/bot/live" -TimeoutSec 10
            $s = $resp.state
            $m = $resp.market
            Write-Output "HEARTBEAT: $(Get-Date -Format 'HH:mm:ss') symbol=$($resp.symbol) status=$($s.status) price=$($m.price) signal=$($m.signal) active=$($s.active_balance) reserve=$($s.reserve_balance) in_position=$($s.in_position) trades=$($s.total_trades) last_run=$($s.last_run_human)"
        } catch {
            Write-Output "ALERT (dashboard): could not reach /bot/live - $($_.Exception.Message)"
        }
        $lastHeartbeat = Get-Date
    }

    Start-Sleep -Seconds 15
}
