$ErrorActionPreference = 'Stop'

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker Desktop is required. Install or start it, then run this script again.'
}

docker info *> $null
if ($LASTEXITCODE -ne 0) {
    throw 'Docker Desktop is not running. Start it, then run this script again.'
}

if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
}

function Set-EnvValue([string]$Name, [string]$Value) {
    $escapedName = [regex]::Escape($Name)
    $contents = Get-Content -LiteralPath '.env' -Raw
    $updated = [regex]::Replace($contents, "(?m)^$escapedName=.*$", "$Name=$Value")

    if ($updated -eq $contents) {
        $updated = $contents.TrimEnd("`r", "`n") + "`r`n$Name=$Value`r`n"
    }

    Set-Content -LiteralPath '.env' -Value $updated -NoNewline
}

Set-EnvValue 'APP_URL' 'http://localhost:8000'
Set-EnvValue 'DB_HOST' 'postgres'

docker compose build
if ($LASTEXITCODE -ne 0) { throw 'The application images failed to build.' }

docker compose up -d --wait postgres pgadmin
if ($LASTEXITCODE -ne 0) { throw 'PostgreSQL or pgAdmin failed to start.' }

docker compose run --rm --no-deps app composer install --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'Composer install failed.' }

docker compose run --rm --no-deps vite npm ci
if ($LASTEXITCODE -ne 0) { throw 'npm install failed.' }

docker compose run --rm --no-deps app php artisan key:generate --force
if ($LASTEXITCODE -ne 0) { throw 'Application key generation failed.' }

docker compose run --rm --no-deps app php artisan migrate --seed --force
if ($LASTEXITCODE -ne 0) { throw 'Database migration failed.' }

docker compose run --rm --no-deps vite npm run build
if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed.' }

docker compose up -d --wait
if ($LASTEXITCODE -ne 0) { throw 'The development stack failed to start.' }

Write-Host ''
Write-Host 'Syntix is ready with Docker.' -ForegroundColor Green
Write-Host 'Application: http://localhost:8000'
Write-Host 'Application admin: admin@syntix.test / password'
Write-Host 'Seeded workspace: SIKLAB 2026 configuration shell (no players, schedules, contests, or scoring staff)'
Write-Host 'Database admin: http://localhost:5050 (admin@example.com / password)'
Write-Host 'Vite hot reload: http://localhost:5173'
Write-Warning 'These credentials are local defaults. Change them before exposing the services beyond this machine.'
