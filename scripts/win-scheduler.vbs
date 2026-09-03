' Dev-box only: runs the Laravel scheduler with no visible console window.
'
' Registered as a per-minute Windows Scheduled Task, this is the local
' equivalent of the production crontab line in docs/DEPLOY.md:
'     * * * * * cd /var/www/cursed-battle && php artisan schedule:run
'
' Per-minute `schedule:run` is deliberate over a single long-lived
' `schedule:work`: each run is independent, so a crashed tick costs one
' minute instead of staying dead until the next logon. Without this,
' energy/health regen silently never ticks.
'
' The wscript host is what makes it invisible; running php.exe directly from
' Task Scheduler flashes a console window every minute.

Set sh = CreateObject("WScript.Shell")
sh.CurrentDirectory = "C:\wamp64\www\BuildSyntax\Cursed Battle\v2"
' 0 = hidden window. True = wait, so Task Scheduler sees the run as still
' in progress and its default "do not start a new instance" policy stops
' slow ticks from piling up.
sh.Run """C:\wamp64\bin\php\php8.3.14\php.exe"" artisan schedule:run", 0, True
