@echo off
REM Invoked every minute by Windows Task Scheduler (task: ApexTradeAI-Scheduler).
REM Laravel's own scheduler (routes/console.php) decides what actually needs
REM to run on each tick (trading:run every 5 min, trading:summary hourly).
cd /d "C:\Apache24\htdocs\ApexTradeAI"
"C:\php-8.4.17-Win32-vs17-x64\php.exe" artisan schedule:run >> "C:\Apache24\htdocs\ApexTradeAI\storage\logs\scheduler.log" 2>&1
