param(
    [string]$Database = '',
    [string]$DbHost = '127.0.0.1',
    [int]$Port = 3307,
    [string]$Username = '',
    [string]$Password = '',
    [string]$OutputDir = '',
    [string]$MariaDbBinDir = 'C:\rbd-runtime\mariadb\bin'
)

$ErrorActionPreference = 'Stop'

$platformRoot = Split-Path -Parent $PSScriptRoot
$envFile = Join-Path $platformRoot '.env'

function Read-DotEnvValue([string]$Path, [string]$Key, [string]$Fallback = '') {
    if (-not (Test-Path $Path)) { return $Fallback }
    foreach ($line in Get-Content $Path) {
        $trimmed = $line.Trim()
        if ($trimmed -eq '' -or $trimmed.StartsWith('#')) { continue }
        $idx = $trimmed.IndexOf('=')
        if ($idx -lt 0) { continue }
        if ($trimmed.Substring(0, $idx).Trim() -eq $Key) {
            return $trimmed.Substring($idx + 1).Trim().Trim('"').Trim("'")
        }
    }
    return $Fallback
}

if ([string]::IsNullOrWhiteSpace($Database)) {
    $Database = Read-DotEnvValue $envFile 'DB_DATABASE' 'rbd_platform'
}
if ([string]::IsNullOrWhiteSpace($Username)) {
    $Username = Read-DotEnvValue $envFile 'DB_USERNAME' 'root'
}
if ([string]::IsNullOrWhiteSpace($Password)) {
    $Password = Read-DotEnvValue $envFile 'DB_PASSWORD' ''
}
if ([string]::IsNullOrWhiteSpace($OutputDir)) {
    $OutputDir = Join-Path $platformRoot 'storage\backups'
}

if (-not (Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
}

$dumpExe = Join-Path $MariaDbBinDir 'mariadb-dump.exe'
if (-not (Test-Path $dumpExe)) {
    throw "mariadb-dump.exe not found at $dumpExe. Standalone MariaDB runtime is required (NO XAMPP)."
}

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$dumpFile = Join-Path $OutputDir "$Database-$timestamp.sql"
$checksumFile = "$dumpFile.sha256"

$argsList = @(
    "--host=$DbHost",
    "--port=$Port",
    "--user=$Username",
    '--single-transaction',
    '--routines',
    '--triggers',
    '--hex-blob',
    "--result-file=$dumpFile"
)
if (-not [string]::IsNullOrEmpty($Password)) {
    $argsList += "--password=$Password"
}
$argsList += $Database

& $dumpExe @argsList
if ($LASTEXITCODE -ne 0) {
    throw "mariadb-dump failed with exit code $LASTEXITCODE"
}

$hash = (Get-FileHash -Path $dumpFile -Algorithm SHA256).Hash.ToLowerInvariant()
$fileName = Split-Path -Leaf $dumpFile
"$hash  $fileName" | Out-File -FilePath $checksumFile -Encoding ascii -NoNewline

Write-Output "BACKUP_FILE=$dumpFile"
Write-Output "CHECKSUM_FILE=$checksumFile"
Write-Output "SHA256=$hash"
