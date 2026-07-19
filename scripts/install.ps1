param(
    [ValidateSet('postgres','mysql')]
    [string]$Database = 'postgres',
    [switch]$PullImages
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker não encontrado.'
}
docker compose version | Out-Null

$Template = if ($Database -eq 'mysql') { '.env.docker.mysql.example' } else { '.env.docker.postgres.example' }
if (-not (Test-Path '.env')) { Copy-Item $Template '.env' }

function Random-Base64([int]$Bytes) {
    $buffer = New-Object byte[] $Bytes
    [Security.Cryptography.RandomNumberGenerator]::Fill($buffer)
    return [Convert]::ToBase64String($buffer)
}

function Set-EnvValue([string]$Key, [string]$Value) {
    $content = Get-Content '.env' -Raw
    $escaped = [Regex]::Escape($Key)
    if ($content -match "(?m)^$escaped=") {
        $content = [Regex]::Replace($content, "(?m)^$escaped=.*$", "$Key=$Value")
    } else {
        $content = $content.TrimEnd() + "`r`n$Key=$Value`r`n"
    }
    Set-Content '.env' $content -Encoding utf8NoBOM
}

$content = Get-Content '.env' -Raw
if ($content -match '(?m)^APP_KEY=$') { Set-EnvValue 'APP_KEY' ('base64:' + (Random-Base64 32)) }
if ($content -match '(?m)^DB_PASSWORD=change-this') { Set-EnvValue 'DB_PASSWORD' ([Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24)).ToLower()) }
if ($content -match '(?m)^RADIUS_CREDENTIAL_KEY=change-this') { Set-EnvValue 'RADIUS_CREDENTIAL_KEY' (Random-Base64 48) }
if ($content -match '(?m)^RADIUS_LOCAL_SECRET=change-this') { Set-EnvValue 'RADIUS_LOCAL_SECRET' (Random-Base64 32) }
if ($content -match '(?m)^SEED_ADMIN_PASSWORD=ChangeMe@123!') {
    $adminPassword = ((Random-Base64 24) -replace '[^A-Za-z0-9]','')
    if ($adminPassword.Length -gt 24) { $adminPassword = $adminPassword.Substring(0, 24) }
    Set-EnvValue 'SEED_ADMIN_PASSWORD' ($adminPassword + 'Aa1!')
}
Set-EnvValue 'COMPOSE_PROFILES' $Database
if ($content -notmatch '(?m)^SEED_ADMIN_LOGIN=') { Set-EnvValue 'SEED_ADMIN_LOGIN' 'admin' }

$profileArgs = @('--profile', $Database)
if ($PullImages) {
    docker compose @profileArgs pull
} else {
    docker compose @profileArgs build --pull app web freeradius
}
docker compose @profileArgs up -d $Database redis
docker compose @profileArgs run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php scripts/check-migration-integrity.php
if ($LASTEXITCODE -ne 0) { throw "Falha na integridade das migrations." }
docker compose @profileArgs run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php artisan migrate --force
docker compose @profileArgs run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php artisan db:seed --force
docker compose @profileArgs run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php artisan radiushub:bootstrap-platform
docker compose @profileArgs up -d --remove-orphans

docker compose @profileArgs ps
Write-Host 'RadiusHub instalado. Consulte login, e-mail e senha inicial no arquivo .env.' -ForegroundColor Green
