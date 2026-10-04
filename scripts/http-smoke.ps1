[CmdletBinding()]
param([int] $Port = 8090)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$log = Join-Path $root 'storage\local-mysql\http-smoke.log'
$err = Join-Path $root 'storage\local-mysql\http-smoke.err.log'
$php = 'C:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $php -PathType Leaf)) { throw "PHP executable not found: $php" }

$server = Start-Process -FilePath $php -ArgumentList @('artisan', 'serve', "--host=127.0.0.1", "--port=$Port") -WorkingDirectory $root -WindowStyle Hidden -RedirectStandardOutput $log -RedirectStandardError $err -PassThru
try {
    $healthy = $false
    for ($i = 0; $i -lt 30; $i++) {
        try {
            $health = Invoke-RestMethod "http://127.0.0.1:$Port/api/health" -TimeoutSec 1
            if ($health.status -eq 'ok') { $healthy = $true; break }
        } catch { }
        Start-Sleep -Milliseconds 500
    }
    if (-not $healthy) { throw "Laravel API did not become healthy; inspect $err" }
    $dashboard = Invoke-RestMethod "http://127.0.0.1:$Port/api/dashboard"
    $forecast = Invoke-RestMethod "http://127.0.0.1:$Port/api/forecast?station_id=ST002&horizon=6"
    [pscustomobject]@{
        health = $health.status
        station_count = $dashboard.summary.station_count
        open_alerts = $dashboard.summary.open_alerts
        forecast_rows = @($forecast.data).Count
        forecast_status = $forecast.data[0].status
    } | ConvertTo-Json -Compress
} finally {
    # `artisan serve` launches a child PHP process; stop only the exact
    # loopback server command so smoke checks never touch another PHP process.
    $pattern = "*-S 127.0.0.1:$Port*"
    Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
        Where-Object { $_.CommandLine -like $pattern -and $_.CommandLine -like "*$root*" } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    if (Get-Process -Id $server.Id -ErrorAction SilentlyContinue) { Stop-Process -Id $server.Id -Force }
}
