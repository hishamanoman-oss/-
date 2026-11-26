<#
.SYNOPSIS
    Starts all standalone local services (NO XAMPP) for the Remote Business Platform
    and opens /system-status in the default browser.
#>
$ErrorActionPreference = 'Stop'

$platformDir = Split-Path -Parent $PSScriptRoot
$phpExe = 'C:\rbd-runtime\php\php.exe'
$mariadbExe = 'C:\rbd-runtime\mariadb\bin\mariadbd.exe'
$mariadbIni = 'C:\rbd-runtime\mariadb\my.ini'
$npmCmd = 'C:\Program Files\nodejs\npm.cmd'

if (-not (Test-Path $phpExe)) { throw "Standalone PHP not found at $phpExe" }
if (-not (Test-Path $mariadbExe)) { throw "Standalone MariaDB not found at $mariadbExe" }

function Test-LocalPort([int]$Port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $iar = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        $wait = $iar.AsyncWaitHandle.WaitOne(300, $false)
        if ($wait -and $client.Connected) { $client.EndConnect($iar); return $true }
        return $false
    } catch {
        return $false
    } finally {
        $client.Dispose()
    }
}

Write-Host "[1/5] Checking standalone MariaDB (127.0.0.1:3307)..." -ForegroundColor Cyan
if (-not (Test-LocalPort 3307)) {
    Start-Process -FilePath $mariadbExe -ArgumentList "--defaults-file=`"$mariadbIni`"", "--console" -WindowStyle Minimized
    Start-Sleep -Seconds 2
}

Write-Host "[2/5] Starting Laravel HTTP Server (127.0.0.1:8000)..." -ForegroundColor Cyan
if (-not (Test-LocalPort 8000)) {
    Start-Process -FilePath $phpExe -ArgumentList "artisan", "serve", "--host=127.0.0.1", "--port=8000" -WorkingDirectory $platformDir -WindowStyle Normal
}

Write-Host "[3/5] Starting Laravel Reverb WebSocket Server (127.0.0.1:8080)..." -ForegroundColor Cyan
if (-not (Test-LocalPort 8080)) {
    Start-Process -FilePath $phpExe -ArgumentList "artisan", "reverb:start", "--host=127.0.0.1", "--port=8080" -WorkingDirectory $platformDir -WindowStyle Normal
}

Write-Host "[4/5] Starting Laravel Queue Worker..." -ForegroundColor Cyan
Start-Process -FilePath $phpExe -ArgumentList "artisan", "queue:work", "--tries=3" -WorkingDirectory $platformDir -WindowStyle Minimized

Write-Host "[5/5] Starting Vite Dev Server (127.0.0.1:5173)..." -ForegroundColor Cyan
if (-not (Test-LocalPort 5173)) {
    Start-Process -FilePath $npmCmd -ArgumentList "run", "dev" -WorkingDirectory $platformDir -WindowStyle Minimized
}

Start-Sleep -Seconds 3
Write-Host "Opening http://127.0.0.1:8000/system-status ..." -ForegroundColor Green
Start-Process "http://127.0.0.1:8000/system-status"
