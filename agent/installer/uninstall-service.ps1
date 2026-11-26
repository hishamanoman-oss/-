param(
    [string]$ServiceName = "RemoteBusinessSyncAgent"
)

$ErrorActionPreference = "Stop"
$svc = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
if (-not $svc) {
    Write-Host "Service $ServiceName is not installed."
    exit 0
}

if ($svc.Status -ne "Stopped") {
    Stop-Service -Name $ServiceName -Force
}

sc.exe delete $ServiceName
Write-Host "Service $ServiceName removed."
