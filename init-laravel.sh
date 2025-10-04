#!/bin/bash

echo "🚀 Inizializzazione Laravel Backend..."

# Copia il file .env.example in .env se non esiste
if [ ! -f "laravel-backend/.env" ]; then
    echo "📝 Creazione file .env..."
    cp laravel-backend/.env.example laravel-backend/.env
fi

# Genera la chiave dell'applicazione
echo "🔑 Generazione chiave applicazione..."
cd laravel-backend
php artisan key:generate

# Installa le dipendenze
echo "📦 Installazione dipendenze..."
composer install

# Esegui le migrazioni
echo "🗄️ Esecuzione migrazioni database..."
php artisan migrate --force

# Popola il database con dati di esempio
echo "🌱 Popolamento database..."
php artisan db:seed --force

# Crea i link simbolici per storage
echo "🔗 Creazione link simbolici..."
php artisan storage:link

# Pulisci e ottimizza la cache
echo "🧹 Pulizia cache..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "✅ Laravel backend inizializzato con successo!"
echo "🌐 Backend disponibile su: http://localhost:8000"
echo "📚 API disponibili su: http://localhost:8000/api/v1"





