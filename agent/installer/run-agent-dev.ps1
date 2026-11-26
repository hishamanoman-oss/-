param(
    [string]$DataDir = "",
    [int]$PollSeconds = 2
)

$ErrorActionPreference = "Stop"
$agentRoot = Split-Path -Parent $PSScriptRoot
$projPath = Join-Path $agentRoot "src\RemoteSyncAgent\RemoteSyncAgent.csproj"

$runArgs = @("run", "--project", $projPath, "--no-restore", "--", "run", "--poll-seconds", "$PollSeconds")
if (-not [string]::IsNullOrWhiteSpace($DataDir)) {
    $runArgs += @("--data-dir", $DataDir)
}

Write-Host "Starting RemoteSyncAgent in foreground development mode (PollInterval=${PollSeconds}s, zero inbound ports)..."
& dotnet @runArgs
