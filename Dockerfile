FROM php:8.3-cli

# Dependencias del sistema + extensiones PHP necesarias
# (pdo_sqlite/sqlite3 para la base de datos, zip/gd/mbstring/xml/bcmath
# para maatwebsite/excel y Laravel en general)
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libsqlite3-dev sqlite3 libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql zip gd mbstring xml bcmath \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiamos el código como fallback (en desarrollo, el volumen del
# docker-compose lo va a pisar, pero esto permite que la imagen
# también sirva "standalone" si hiciera falta)
COPY . .

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]