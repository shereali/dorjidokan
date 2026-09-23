#!/bin/sh
# Server-side deploy script for dorjidokan. Invoked by GitHub Actions.
set -e

cd /var/www/dorjidokan

# fetch + reset --hard, NOT git pull — deterministically matches the server to
# origin/main every time, never blocks on a merge conflict from a hand-edit
git fetch origin main
git reset --hard origin/main

docker compose up -d --build
docker compose ps

mkdir -p /var/log/dorjidokan
echo "$(date -u '+%Y-%m-%d %H:%M:%S UTC') deploy complete" >> /var/log/dorjidokan/deploy.log
