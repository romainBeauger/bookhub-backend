#!/bin/bash
set -e

mkdir -p config/jwt
if [ ! -f config/jwt/private.pem ]; then
    openssl genrsa -passout pass:${JWT_PASSPHRASE:-secret} -out config/jwt/private.pem 4096
    openssl rsa -passin pass:${JWT_PASSPHRASE:-secret} -in config/jwt/private.pem -pubout -out config/jwt/public.pem
fi

APP_ENV=prod php bin/console cache:warmup --no-debug 2>/dev/null || true

exec php -d variables_order=EGPCS -S "0.0.0.0:${PORT:-8080}" public/index.php
