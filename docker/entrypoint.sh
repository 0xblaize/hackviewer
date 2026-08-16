#!/bin/sh
set -eu

DATABASE_PATH="${DATABASE_PATH:-/var/www/html/database/app.sqlite}"
CACHE_DIR=/var/www/html/storage/cache
COMMIT_MARKER=$CACHE_DIR/source-sync-commit
mkdir -p "$(dirname "$DATABASE_PATH")" /var/www/html/storage/raw /var/www/html/storage/logs "$CACHE_DIR"
php /var/www/html/bin/migrate.php

SYNC_ARGS=
if [ -n "${RENDER_GIT_COMMIT:-}" ] && { [ ! -f "$COMMIT_MARKER" ] || [ "$(cat "$COMMIT_MARKER")" != "$RENDER_GIT_COMMIT" ]; }; then
    SYNC_ARGS=--force
fi

if php /var/www/html/bin/sync.php $SYNC_ARGS; then
    if [ -n "${RENDER_GIT_COMMIT:-}" ]; then
        printf '%s' "$RENDER_GIT_COMMIT" > "$COMMIT_MARKER"
    fi
else
    printf '%s\n' 'Source sync reported errors; starting the web service with existing data.' >&2
fi

chown -R www-data:www-data "$(dirname "$DATABASE_PATH")" /var/www/html/storage
exec apache2-foreground
