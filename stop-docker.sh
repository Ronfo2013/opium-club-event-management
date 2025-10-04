#!/bin/bash

# Script per fermare l'ambiente Docker

echo "🛑 Fermando ambiente Docker per Opium Club Event Management..."
echo "=================================================="

# Ferma i container
docker-compose down

echo "✅ Container fermati con successo!"

# Opzione per rimuovere anche i volumi (dati del database)
read -p "Vuoi rimuovere anche i volumi (dati del database)? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "🗑️ Rimuovendo volumi..."
    docker-compose down -v
    echo "✅ Volumi rimossi!"
fi

echo "🎉 Ambiente Docker fermato!"