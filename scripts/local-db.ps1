[CmdletBinding()]
param(
    [ValidateSet('init', 'start', 'stop', 'status')]
    [string] $Action = 'status',
    [int] $Port = 3307,
    # These passwords are disposable local defaults for the isolated helper;
    # pass explicit values for a shared workstation and never reuse in prod.
    [string] $RootPassword = 'root',
    [string] $AppPassword = 'urban'
)

$ErrorActionPreference = 'Stop'

# This helper owns only web/storage/local-mysql. It never touches XAMPP's
# existing data directory or attempts to stop an unrelated mysqld process.
$WebRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$LocalRoot = Join-Path $WebRoot 'storage\local-mysql'
$DataDir = Join-Path $LocalRoot 'data'
$PidFile = Join-Path $LocalRoot 'mysqld.pid'
$ErrorLog = Join-Path $LocalRoot 'mysqld.err.log'
$OutputLog = Join-Path $LocalRoot 'mysqld.out.log'
$MariaBin = 'C:\xampp\mysql\bin'
$Mysqld = Join-Path $MariaBin 'mysqld.exe'
$InstallDb = Join-Path $MariaBin 'mysql_install_db.exe'
$Mysql = Join-Path $MariaBin 'mysql.exe'
$MysqlAdmin = Join-Path $MariaBin 'mysqladmin.exe'

function Assert-Tools {
    foreach ($tool in @($Mysqld, $InstallDb, $Mysql, $MysqlAdmin)) {
        if (-not (Test-Path -LiteralPath $tool -PathType Leaf)) {
            throw "MariaDB tool not found: $tool"
        }
    }
}

function Assert-LocalPath([string] $Path) {
    $resolved = [IO.Path]::GetFullPath($Path)
    $root = [IO.Path]::GetFullPath($LocalRoot).TrimEnd('\')
    $prefix = $root + '\'
    if ($resolved -ne $root -and -not $resolved.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to operate outside isolated local DB directory: $resolved"
    }
}

function Ensure-Directories {
    New-Item -ItemType Directory -Force -Path $LocalRoot, $DataDir | Out-Null
    foreach ($path in @($LocalRoot, $DataDir, $PidFile, $ErrorLog, $OutputLog)) { Assert-LocalPath $path }
}

function Is-Initialized {
    return (Test-Path -LiteralPath (Join-Path $DataDir 'mysql') -PathType Container)
}

function Get-LocalProcess {
    if (-not (Test-Path -LiteralPath $PidFile -PathType Leaf)) { return $null }
    $localProcessId = [int](Get-Content -LiteralPath $PidFile -Raw).Trim()
    $process = Get-CimInstance Win32_Process -Filter "ProcessId=$localProcessId" -ErrorAction SilentlyContinue
    if ($null -eq $process) { return $null }
    if ($process.Name -ne 'mysqld.exe' -or ($process.CommandLine -notlike "*$DataDir*")) {
        throw "PID file does not identify this isolated mysqld; refusing to stop PID $localProcessId"
    }
    return $process
}

function Wait-Ready {
    for ($i = 0; $i -lt 30; $i++) {
        & $MysqlAdmin '--protocol=tcp' "--host=127.0.0.1" "--port=$Port" '--user=root' "--password=$RootPassword" 'ping' 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { return }
        Start-Sleep -Milliseconds 500
    }
    throw "MariaDB did not become ready on 127.0.0.1:$Port; inspect $ErrorLog"
}

Assert-Tools
Ensure-Directories

switch ($Action) {
    'init' {
        if (Is-Initialized) {
            Write-Output "Already initialized: $DataDir"
            break
        }
        & $InstallDb "--datadir=$DataDir" "--password=$RootPassword" "--port=$Port"
        if ($LASTEXITCODE -ne 0) { throw "mysql_install_db.exe failed with exit code $LASTEXITCODE" }
        Write-Output "Initialized MariaDB data directory: $DataDir"
    }
    'start' {
        if (-not (Is-Initialized)) { & $PSCommandPath init -Port $Port -RootPassword $RootPassword -AppPassword $AppPassword }
        $existing = Get-LocalProcess
        if ($existing) {
            Wait-Ready
            Write-Output "Already running: PID $($existing.ProcessId), port $Port"
            break
        }
        if (Test-Path -LiteralPath $PidFile) { Remove-Item -LiteralPath $PidFile -Force }
        $args = @("--datadir=$DataDir", "--port=$Port", '--bind-address=127.0.0.1', '--skip-name-resolve', "--pid-file=$PidFile", "--log-error=$ErrorLog", '--console')
        $proc = Start-Process -FilePath $Mysqld -ArgumentList $args -WindowStyle Hidden -RedirectStandardOutput $OutputLog -RedirectStandardError $ErrorLog -PassThru
        Wait-Ready
        $escapedAppPassword = $AppPassword.Replace("'", "''")
        $sql = "CREATE DATABASE IF NOT EXISTS urban_observatory; CREATE USER IF NOT EXISTS 'urban'@'127.0.0.1' IDENTIFIED BY '$escapedAppPassword'; ALTER USER 'urban'@'127.0.0.1' IDENTIFIED BY '$escapedAppPassword'; GRANT ALL PRIVILEGES ON urban_observatory.* TO 'urban'@'127.0.0.1'; FLUSH PRIVILEGES;"
        $sql | & $Mysql '--protocol=tcp' '--host=127.0.0.1' "--port=$Port" '--user=root' "--password=$RootPassword"
        if ($LASTEXITCODE -ne 0) { throw "Could not provision urban_observatory account" }
        Write-Output "Started MariaDB PID $($proc.Id) on 127.0.0.1:$Port"
        Write-Output "Laravel local DB: DB_CONNECTION=mysql; DB_HOST=127.0.0.1; DB_PORT=$Port; DB_DATABASE=urban_observatory; DB_USERNAME=urban; DB_PASSWORD=(the -AppPassword value)"
    }
    'stop' {
        $process = Get-LocalProcess
        if ($null -eq $process) {
            Write-Output 'Isolated MariaDB is not running.'
            break
        }
        Stop-Process -Id $process.ProcessId -Force
        Remove-Item -LiteralPath $PidFile -Force -ErrorAction SilentlyContinue
        Write-Output "Stopped isolated MariaDB PID $($process.ProcessId)."
    }
    'status' {
        $process = Get-LocalProcess
        if ($process) {
            Write-Output "running pid=$($process.ProcessId) port=$Port data=$DataDir"
            & $MysqlAdmin '--protocol=tcp' '--host=127.0.0.1' "--port=$Port" '--user=root' "--password=$RootPassword" 'version' 2>$null
        } else {
            Write-Output "stopped initialized=$(Is-Initialized) data=$DataDir"
        }
    }
}
