$ErrorActionPreference = 'Stop'

$herdCommand = Get-Command herd -ErrorAction SilentlyContinue
$herdExecutable = if ($herdCommand) {
    $herdCommand.Source
} else {
    Join-Path $env:USERPROFILE '.config\herd\bin\herd.bat'
}

if (-not (Test-Path -LiteralPath $herdExecutable)) {
    throw 'Laravel Herd is not installed or has not completed its first-run setup.'
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker is required only for the PostgreSQL service. Install or start Docker Desktop and run this script again.'
}

if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
}

docker compose up -d postgres pgadmin
if ($LASTEXITCODE -ne 0) { throw 'PostgreSQL or pgAdmin failed to start.' }

& $herdExecutable composer install
if ($LASTEXITCODE -ne 0) { throw 'Composer install failed.' }

& $herdExecutable php artisan key:generate --force
& $herdExecutable php artisan migrate --seed --force

npm install
npm run build

Write-Host ''
Write-Host 'Syntix is ready for Herd.' -ForegroundColor Green
Write-Host 'Open Herd, link this directory as "syntix", then visit http://syntix.test.'
Write-Host 'Database admin: http://localhost:5050 (admin@example.com / password)'
Write-Host 'For frontend development, run: npm run dev'
