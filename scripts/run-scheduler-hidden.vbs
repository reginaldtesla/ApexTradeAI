' Runs run-scheduler.bat with its window hidden (window style 0), so Windows
' Task Scheduler firing this every minute doesn't flash a console window.
Set objShell = CreateObject("WScript.Shell")
objShell.Run """C:\Apache24\htdocs\ApexTradeAI\scripts\run-scheduler.bat""", 0, True
