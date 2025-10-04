#!/bin/bash

# Script per avviare l'ambiente Docker completo per Opium Club Event Management

echo "🚀 Avviando ambiente Docker per Opium Club Event Management..."
echo "=================================================="

# Controlla se Docker è installato
if ! command -v docker &> /dev/null; then
    echo "❌ Docker non è installato!"
    echo "Installa Docker Desktop da: https://www.docker.com/products/docker-desktop/"
    exit 1
fi

# Controlla se Docker è avviato
if ! docker info &> /dev/null; then
    echo "❌ Docker non è avviato!"
    echo "Avvia Docker Desktop e riprova."
    exit 1
fi

# Crea file .env per Laravel se non esiste
if [ ! -f laravel-backend/.env ]; then
    echo "📝 Creando file .env per Laravel..."
    cp laravel-backend/.env.example laravel-backend/.env
    echo "✅ File .env creato!"
fi

# Crea file .env per React se non esiste
if [ ! -f react-frontend/.env ]; then
    echo "📝 Creando file .env per React..."
    cat > react-frontend/.env << EOF
REACT_APP_API_URL=http://localhost:8000/api/v1
GENERATE_SOURCEMAP=false
EOF
    echo "✅ File .env per React creato!"
fi

echo "🐳 Costruendo e avviando i container..."
echo "Questo potrebbe richiedere qualche minuto la prima volta..."

# Avvia i container
docker-compose up --build -d

# Aspetta che i container siano pronti
echo "⏳ Aspettando che i servizi siano pronti..."
sleep 15

# Controlla lo stato
echo ""
echo "📊 Stato dei container:"
docker-compose ps

# Genera chiave applicazione Laravel
echo ""
echo "🔑 Generando chiave applicazione Laravel..."
docker-compose exec app php artisan key:generate

# Esegui migrazioni
echo ""
echo "🗄️ Eseguendo migrazioni database..."
docker-compose exec app php artisan migrate --force

# Esegui seeders
echo ""
echo "🌱 Eseguendo seeders..."
docker-compose exec app php artisan db:seed --force

# Crea link simbolico per storage
echo ""
echo "🔗 Creando link simbolico per storage..."
docker-compose exec app php artisan storage:link

echo ""
echo "🎉 Ambiente Docker avviato con successo!"
echo "=================================================="
echo "🌐 Frontend React:     http://localhost:3000"
echo "🔧 Backend Laravel:    http://localhost:8000"
echo "🗄️ PHPMyAdmin:         http://localhost:8080"
echo "   └─ User: root, Password: docker_password"
echo "📧 Mailhog:            http://localhost:8025"
echo ""
echo "📝 Comandi utili:"
echo "   • Vedi i log:       docker-compose logs -f"
echo "   • Ferma tutto:      docker-compose down"
echo "   • Riavvia app:      docker-compose restart app"
echo "   • Riavvia frontend: docker-compose restart frontend"
echo ""
echo "🔧 Per vedere modifiche in tempo reale:"
echo "   1. Modifica qualsiasi file"
echo "   2. Salva (Ctrl+S / Cmd+S)"
echo "   3. Refresh browser (F5)"
echo "   4. Vedi le modifiche istantaneamente!"
echo ""

# Apri automaticamente il browser (macOS)
if [[ "$OSTYPE" == "darwin"* ]]; then
    echo "🌐 Aprendo il browser..."
    open http://localhost:3000
fi