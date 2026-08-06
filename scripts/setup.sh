#!/usr/bin/env sh
set -eu

repository_root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$repository_root"

[ -f .env ] || cp .env.example .env
[ -f backend/.env ] || cp backend/.env.example backend/.env

docker compose build app
docker compose up -d db
docker compose run --rm app composer install --no-interaction --prefer-dist
docker compose run --rm app php artisan key:generate --force
docker compose run --rm app php artisan migrate --seed --force
docker compose up -d app nginx

printf '%s\n' 'Fandoogh foundation is available at http://localhost:8080/up'
