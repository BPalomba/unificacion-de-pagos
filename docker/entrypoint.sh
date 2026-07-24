#!/bin/sh
set -e

# Instala dependencias si el volumen de vendor está vacío
# (primera vez que se levanta el contenedor)
if [ ! -f vendor/autoload.php ]; then
    echo "Instalando dependencias de Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Crea el .env si no existe
if [ ! -f .env ]; then
    echo "Creando .env..."
    cp .env.example .env
fi

# Genera la APP_KEY si todavía no está seteada
if ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
    echo "Generando APP_KEY..."
    php artisan key:generate
fi

# Crea el archivo de la base SQLite si no existe
mkdir -p database
touch database/database.sqlite

# Corre las migraciones pendientes
echo "Corriendo migraciones..."
php artisan migrate --force

exec "$@"