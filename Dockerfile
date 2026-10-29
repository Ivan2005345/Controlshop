# PHP 8.2 + Apache. Render construye esta imagen y la ejecuta.
FROM php:8.2-apache

# Extensión para hablar con PostgreSQL (Supabase)
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

# Configuración de PHP para producción (no muestra errores en pantalla)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Apache sirve SOLO la carpeta /public (src/ y database/ quedan fuera del alcance de internet)
# y escucha en el puerto que Render indica con la variable PORT.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV PORT=10000
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && sed -ri -e 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri -e 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/hide-version.conf \
 && a2enconf hide-version

COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000
