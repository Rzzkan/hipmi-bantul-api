#!/usr/bin/env bash
# Jalankan di server (folder repo) setiap kali update: bash deploy/deploy.sh
set -euo pipefail

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan filament:optimize
php artisan optimize
php artisan queue:restart || true
echo "✅ API api.hipmibantul.com ter-update"
