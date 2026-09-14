#!/bin/bash
set -e

echo "🚀 Starting deployment..."

# Directory navigation (ensures running from project root)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# 1. Put application into maintenance mode (optional, graceful)
echo "🔒 Enabling maintenance mode..."
php artisan down --retry=10 || true

# 2. Pull latest changes from Git
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "production")
echo "📥 Pulling latest code from branch '$CURRENT_BRANCH'..."
git pull origin "$CURRENT_BRANCH"

# 3. Install/update PHP dependencies
echo "📦 Installing composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# 4. Run database migrations
echo "🗄️ Running migrations..."
php artisan migrate --force

# 5. Build frontend assets if npm is installed
if command -v npm &> /dev/null; then
    echo "🎨 Building frontend assets..."
    npm install --no-audit
    npm run build
fi

# 6. Clear and rebuild Laravel production caches
echo "⚡ Optimizing Laravel caches..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Ensure storage symlink & correct permissions
php artisan storage:link || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# 8. Bring application back live
echo "🔓 Bringing application live..."
php artisan up

echo "🎉 Deployment successfully completed!"
