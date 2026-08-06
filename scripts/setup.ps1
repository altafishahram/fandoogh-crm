$ErrorActionPreference = 'Stop'

$repositoryRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $repositoryRoot

if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
}

if (-not (Test-Path -LiteralPath 'backend/.env')) {
    Copy-Item -LiteralPath 'backend/.env.example' -Destination 'backend/.env'
}

docker compose build app
docker compose up -d db
docker compose run --rm app composer install --no-interaction --prefer-dist
docker compose run --rm app php artisan key:generate --force
docker compose run --rm app php artisan migrate --seed --force
docker compose up -d app nginx

Write-Output 'Fandoogh foundation is available at http://localhost:8080/up'
