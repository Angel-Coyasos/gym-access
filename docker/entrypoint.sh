#!/bin/sh
set -e

echo "──────────────────────────────────────────"
echo "  🏋️  Gym Access System — Iniciando..."
echo "──────────────────────────────────────────"

# Directorio de trabajo
cd /var/www

# ─── 1. Instalar dependencias si no existen ──────────────────────────────────
if [ ! -f "vendor/autoload.php" ]; then
    echo "📦 Instalando dependencias Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# ─── 2. Copiar .env si no existe ─────────────────────────────────────────────
if [ ! -f ".env" ]; then
    echo "⚙️  Copiando .env.example → .env"
    cp .env.example .env
fi

# ─── 3. Generar APP_KEY si no está seteada ───────────────────────────────────
if grep -q "APP_KEY=$" .env || grep -q "APP_KEY=base64:$" .env; then
    echo "🔑 Generando APP_KEY..."
    php artisan key:generate --force
fi

# ─── 4. Esperar a que la base de datos esté lista ────────────────────────────
echo "⏳ Esperando a PostgreSQL..."
until php artisan db:monitor --max=1 2>/dev/null; do
    echo "   DB no disponible aún, reintentando en 3s..."
    sleep 3
done
echo "✅ PostgreSQL disponible."

# ─── 5. Correr migraciones ───────────────────────────────────────────────────
echo "🗄️  Ejecutando migraciones..."
php artisan migrate --force --no-interaction

# ─── 6. Limpiar y cachear configuración ─────────────────────────────────────
echo "🚀 Optimizando configuración..."
php artisan config:cache
php artisan route:cache
php artisan event:cache

# ─── 7. Crear directorios necesarios ─────────────────────────────────────────
mkdir -p /var/log/supervisor
mkdir -p storage/logs
chmod -R 775 storage bootstrap/cache

echo "──────────────────────────────────────────"
echo "  ✅ Setup completo. Iniciando servicios..."
echo "──────────────────────────────────────────"

# Ejecutar el comando pasado (supervisord)
exec "$@"