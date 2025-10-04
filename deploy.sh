#!/bin/bash

# Script per deploy su Google App Engine

echo "🚀 Deploy su Google App Engine per Opium Club Event Management..."
echo "=============================================================="

# Controlla se gcloud è installato
if ! command -v gcloud &> /dev/null; then
    echo "❌ Google Cloud CLI non è installato!"
    echo "Installa Google Cloud CLI da: https://cloud.google.com/sdk/docs/install"
    exit 1
fi

# Controlla se l'utente è autenticato
if ! gcloud auth list --filter=status:ACTIVE --format="value(account)" | grep -q .; then
    echo "❌ Non sei autenticato con Google Cloud!"
    echo "Esegui: gcloud auth login"
    exit 1
fi

# Imposta il progetto
PROJECT_ID="premium-origin-471808-u0"
echo "📋 Impostando progetto: $PROJECT_ID"
gcloud config set project $PROJECT_ID

# Genera chiave applicazione se non esiste
if [ ! -f laravel-backend/.env.production ]; then
    echo "📝 Creando file .env.production..."
    cp laravel-backend/.env.example laravel-backend/.env.production
    
    # Genera chiave applicazione
    echo "🔑 Generando chiave applicazione..."
    cd laravel-backend
    php artisan key:generate --env=production
    cd ..
fi

# Build del frontend React
echo "🏗️ Building frontend React..."
cd react-frontend
npm install
npm run build
cd ..

# Copia i file build del frontend nel backend
echo "📦 Copiando file build nel backend..."
cp -r react-frontend/build/* laravel-backend/public/

# Deploy del backend Laravel
echo "🚀 Deploying backend Laravel su App Engine..."
cd laravel-backend

# Esegui migrazioni prima del deploy
echo "🗄️ Eseguendo migrazioni..."
gcloud app deploy --quiet --no-promote

# Promuovi la versione
echo "✅ Promuovendo versione..."
gcloud app services set-traffic default --splits=1

cd ..

echo ""
echo "🎉 Deploy completato con successo!"
echo "=============================================================="
echo "🌐 URL dell'applicazione:"
gcloud app browse --no-launch-browser

echo ""
echo "📝 Comandi utili:"
echo "   • Vedi i log:       gcloud app logs tail -s default"
echo "   • Vedi le versioni: gcloud app versions list"
echo "   • Rollback:         gcloud app services set-traffic default --splits=0"
echo ""






