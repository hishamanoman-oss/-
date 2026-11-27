param(
    [Parameter(Mandatory = $true)]
    [string]$BackupFile,
    [Parameter(Mandatory = $true)]
    [string]$TargetDatabase,
    [string]$DbHost = '127.0.0.1',
    [int]$Port = 3307,
    [string]$Username = '',
    [string]$Password = '',
    [string]$MariaDbBinDir = 'C:\rbd-runtime\mariadb\bin'
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $BackupFile)) {
    throw "Backup file not found: $BackupFile"
}

$checksumFile = "$BackupFile.sha256"
if (-not (Test-Path $checksumFile)) {
    throw "Checksum file missing: $checksumFile"
}

$expectedHash = ((Get-Content $checksumFile -Raw).Trim() -split '\s+')[0].ToLowerInvariant()
$actualHash = (Get-FileHash -Path $BackupFile -Algorithm SHA256).Hash.ToLowerInvariant()

if ($expectedHash -ne $actualHash) {
    throw "SHA-256 integrity verification FAILED! Expected=$expectedHash Actual=$actualHash"
}

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

if ([string]::IsNullOrWhiteSpace($Username)) {
    $Username = Read-DotEnvValue $envFile 'DB_USERNAME' 'root'
    if ([string]::IsNullOrEmpty($Password)) {
        $Password = Read-DotEnvValue $envFile 'DB_PASSWORD' ''
    }
}

$clientExe = Join-Path $MariaDbBinDir 'mariadb.exe'
if (-not (Test-Path $clientExe)) {
    throw "mariadb.exe not found at $clientExe"
}

$authArgs = @("--host=$DbHost", "--port=$Port", "--user=$Username")
if (-not [string]::IsNullOrEmpty($Password)) {
    $authArgs += "--password=$Password"
}

# Create clean target database if needed
$createSql = "CREATE DATABASE IF NOT EXISTS ``$TargetDatabase`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& $clientExe @authArgs -e $createSql
if ($LASTEXITCODE -ne 0) {
    throw "Failed to prepare target database $TargetDatabase"
}

# Restore SQL dump cleanly using source command inside mariadb client
$escapedPath = $BackupFile.Replace('\', '/')
$restoreArgs = $authArgs + @($TargetDatabase, '-e', "SOURCE $escapedPath;")
& $clientExe @restoreArgs
if ($LASTEXITCODE -ne 0) {
    throw "Restore into $TargetDatabase failed with exit code $LASTEXITCODE"
}

Write-Output "SHA256_VERIFIED=$actualHash"
Write-Output "RESTORED_DATABASE=$TargetDatabase"
