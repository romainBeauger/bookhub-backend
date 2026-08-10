#!/bin/bash
set -e

mkdir -p config/jwt
if [ ! -f config/jwt/private.pem ]; then
    openssl genrsa -passout pass:${JWT_PASSPHRASE:-secret} -out config/jwt/private.pem 4096
    openssl rsa -passin pass:${JWT_PASSPHRASE:-secret} -in config/jwt/private.pem -pubout -out config/jwt/public.pem
    chown www-data:www-data config/jwt/*.pem
fi

PORT=${PORT:-80}
sed -i "s/\*:80>/\*:${PORT}>/g" /etc/apache2/sites-available/000-default.conf
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf

APP_ENV=prod php bin/console cache:warmup --no-debug 2>/dev/null || true

exec "$@"
