#!/bin/sh
set -e

if [ "$(id -u)" = '0' ]; then
    # Klasör izinlerini düzelt
    chown -R www-data:www-data /app/storage /app/bootstrap/cache

    # Gosu veya setpriv ile www-data kullanıcısına geçerek işlemlere devam et
    if command -v gosu >/dev/null 2>&1; then
        exec gosu www-data "$0" "$@"
    elif command -v setpriv >/dev/null 2>&1; then
        exec setpriv --reuid=www-data --regid=www-data --init-groups "$0" "$@"
    fi
fi

# Prod optimizasyonları
php artisan optimize

# Storage symlink (yeniden başlatmalarda hata vermemesi için --force)
php artisan storage:link --force

exec "$@"