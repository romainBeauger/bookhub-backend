FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev openssl \
    && docker-php-ext-install intl pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . .

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-scripts \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var/

RUN printf '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options -MultiViews -Indexes\n\
        RewriteEngine On\n\
        RewriteCond %%{REQUEST_FILENAME} !-f\n\
        RewriteRule ^ index.php [QSA,L]\n\
    </Directory>\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

COPY docker-entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
