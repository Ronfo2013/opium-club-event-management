#!/bin/bash

# Script di avvio per simulare Cloud Run

echo "🚀 Avviando simulatore Cloud Run per Opium Club..."

# Configura Apache per porta 8080
sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf
sed -i 's/:80/:8080/g' /etc/apache2/sites-available/000-default.conf

# Configura PHP per produzione
echo "📝 Configurando PHP per produzione..."
echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/production.ini
echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/production.ini
echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/production.ini
echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/production.ini
echo "opcache.enable = 1" >> /usr/local/etc/php/conf.d/production.ini
echo "opcache.memory_consumption = 128" >> /usr/local/etc/php/conf.d/production.ini
echo "opcache.interned_strings_buffer = 8" >> /usr/local/etc/php/conf.d/production.ini
echo "opcache.max_accelerated_files = 4000" >> /usr/local/etc/php/conf.d/production.ini
echo "opcache.revalidate_freq = 2" >> /usr/local/etc/php/conf.d/production.ini
echo "opcache.fast_shutdown = 1" >> /usr/local/etc/php/conf.d/production.ini

# Attiva OPcache
echo "🔧 Attivando OPcache..."
echo "opcache.enable_cli = 1" >> /usr/local/etc/php/conf.d/production.ini

# Configura Laravel per produzione
cd /var/www/html/laravel-backend

# Genera chiave se non esiste
if [ ! -f .env ]; then
    echo "📝 Creando file .env per produzione..."
    cp .env.example .env
fi

# Genera chiave applicazione
echo "🔑 Generando chiave applicazione..."
php artisan key:generate --force

# Configura cache per produzione
echo "⚡ Configurando cache per produzione..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Crea link simbolico per storage
echo "🔗 Creando link simbolico storage..."
php artisan storage:link

# Imposta permessi corretti
echo "🔒 Impostando permessi..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 777 storage bootstrap/cache

# Avvia Apache in foreground (Cloud Run requirement)
echo "🌐 Avviando Apache..."
echo "=========================================="
echo "🚀 Simulatore Cloud Run avviato!"
echo "🌐 URL: http://localhost:8080"
echo "🔍 Health Check: http://localhost:8080/api/v1/health"
echo "📊 API Events: http://localhost:8080/api/v1/events"
echo "=========================================="

exec apache2-foreground
