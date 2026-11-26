param(
    [string]$ServiceName = "RemoteBusinessSyncAgent",
    [string]$DisplayName = "Remote Business Platform Local Sync Agent",
    [string]$InstallDir = "C:\ProgramData\RemoteBusinessPlatform\SyncAgentApp",
    [string]$DataDir = "C:\ProgramData\RemoteBusinessPlatform\SyncAgentData"
)

$ErrorActionPreference = "Stop"
$agentRoot = Split-Path -Parent $PSScriptRoot
$projPath = Join-Path $agentRoot "src\RemoteSyncAgent\RemoteSyncAgent.csproj"

Write-Host "Publishing RemoteSyncAgent (.NET 10 Windows) to $InstallDir ..."
dotnet publish $projPath -c Release -r win-x64 --self-contained false -o $InstallDir

New-Item -ItemType Directory -Force -Path $DataDir | Out-Null
$exePath = Join-Path $InstallDir "RemoteSyncAgent.exe"
$binPathArg = "`"$exePath`" run --data-dir `"$DataDir`" --dpapi-scope LocalMachine"

$existing = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "Stopping and replacing existing service $ServiceName ..."
    Stop-Service -Name $ServiceName -Force -ErrorAction SilentlyContinue
    sc.exe delete $ServiceName | Out-Null
    Start-Sleep -Seconds 2
}

sc.exe create $ServiceName binPath= $binPathArg start= auto DisplayName= $DisplayName
sc.exe failure $ServiceName reset= 86400 actions= restart/5000/restart/15000/restart/60000
Write-Host "Service $ServiceName installed. Enroll first with --dpapi-scope LocalMachine --data-dir `"$DataDir`", then run: Start-Service $ServiceName"
