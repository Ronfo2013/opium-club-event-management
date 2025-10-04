#!/bin/bash

# Script per testare l'ambiente Cloud Run simulato

echo "🧪 Testando simulatore Cloud Run per Opium Club..."
echo "================================================"

# Controlla se il container è attivo
if ! docker ps | grep -q opium-cloudrun-sim; then
    echo "❌ Container Cloud Run non è attivo!"
    echo "Avvia prima con: docker-compose -f docker-compose.cloudrun.yml up -d"
    exit 1
fi

echo "✅ Container Cloud Run attivo"
echo ""

# Test 1: Health Check
echo "🔍 Test 1: Health Check"
echo "-----------------------"
HEALTH_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null http://localhost:8080/api/v1/health)
if [ "$HEALTH_RESPONSE" = "200" ]; then
    echo "✅ Health check: OK"
else
    echo "❌ Health check: FAILED (HTTP $HEALTH_RESPONSE)"
fi
echo ""

# Test 2: API Events
echo "🔍 Test 2: API Events"
echo "---------------------"
EVENTS_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null http://localhost:8080/api/v1/events)
if [ "$EVENTS_RESPONSE" = "200" ]; then
    echo "✅ API Events: OK"
    echo "📊 Eventi disponibili:"
    curl -s http://localhost:8080/api/v1/events | jq '.' 2>/dev/null || curl -s http://localhost:8080/api/v1/events
else
    echo "❌ API Events: FAILED (HTTP $EVENTS_RESPONSE)"
fi
echo ""

# Test 3: Database Connection
echo "🔍 Test 3: Database Connection"
echo "-----------------------------"
DB_TEST=$(docker exec opium-cloudrun-sim php -r "
try {
    \$pdo = new PDO('mysql:host=cloudsql-mysql;dbname=opium_events', 'root', 'docker_password');
    echo 'OK';
} catch (Exception \$e) {
    echo 'FAILED: ' . \$e->getMessage();
}
")
if [ "$DB_TEST" = "OK" ]; then
    echo "✅ Database Connection: OK"
else
    echo "❌ Database Connection: $DB_TEST"
fi
echo ""

# Test 4: Redis Connection
echo "🔍 Test 4: Redis Connection"
echo "--------------------------"
REDIS_TEST=$(docker exec opium-cloudrun-sim php -r "
try {
    \$redis = new Redis();
    \$redis->connect('cloudsql-redis', 6379);
    \$redis->ping();
    echo 'OK';
} catch (Exception \$e) {
    echo 'FAILED: ' . \$e->getMessage();
}
")
if [ "$REDIS_TEST" = "OK" ]; then
    echo "✅ Redis Connection: OK"
else
    echo "❌ Redis Connection: $REDIS_TEST"
fi
echo ""

# Test 5: File Permissions
echo "🔍 Test 5: File Permissions"
echo "--------------------------"
PERM_TEST=$(docker exec opium-cloudrun-sim bash -c "
if [ -w /var/www/html/laravel-backend/storage ] && [ -w /var/www/html/laravel-backend/bootstrap/cache ]; then
    echo 'OK';
else
    echo 'FAILED';
fi
")
if [ "$PERM_TEST" = "OK" ]; then
    echo "✅ File Permissions: OK"
else
    echo "❌ File Permissions: $PERM_TEST"
fi
echo ""

# Test 6: Static Files
echo "🔍 Test 6: Static Files"
echo "----------------------"
STATIC_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null http://localhost:8080/favicon.ico)
if [ "$STATIC_RESPONSE" = "200" ] || [ "$STATIC_RESPONSE" = "404" ]; then
    echo "✅ Static Files: OK (serving correctly)"
else
    echo "❌ Static Files: FAILED (HTTP $STATIC_RESPONSE)"
fi
echo ""

# Test 7: Frontend Build
echo "🔍 Test 7: Frontend Build"
echo "------------------------"
if docker exec opium-cloudrun-sim test -f /var/www/html/laravel-backend/public/static/js/main.*.js; then
    echo "✅ Frontend Build: OK"
else
    echo "❌ Frontend Build: FAILED (React build not found)"
fi
echo ""

# Test 8: Laravel Cache
echo "🔍 Test 8: Laravel Cache"
echo "-----------------------"
CACHE_TEST=$(docker exec opium-cloudrun-sim bash -c "
cd /var/www/html/laravel-backend && php artisan config:show | head -5
")
if [ $? -eq 0 ]; then
    echo "✅ Laravel Cache: OK"
    echo "📋 Config cached successfully"
else
    echo "❌ Laravel Cache: FAILED"
fi
echo ""

# Test 9: Performance Test
echo "🔍 Test 9: Performance Test"
echo "--------------------------"
echo "Testando risposta API (5 richieste)..."
START_TIME=$(date +%s%N)
for i in {1..5}; do
    curl -s http://localhost:8080/api/v1/health > /dev/null
done
END_TIME=$(date +%s%N)
DURATION=$((($END_TIME - $START_TIME) / 1000000))
echo "⏱️  Tempo medio per richiesta: $((DURATION / 5))ms"
if [ $DURATION -lt 5000 ]; then
    echo "✅ Performance: OK (sotto 1s per 5 richieste)"
else
    echo "⚠️  Performance: LENTA (sopra 1s per 5 richieste)"
fi
echo ""

# Test 10: Memory Usage
echo "🔍 Test 10: Memory Usage"
echo "-----------------------"
MEMORY_USAGE=$(docker exec opium-cloudrun-sim bash -c "
ps aux | grep apache2 | head -1 | awk '{print \$4}'
")
echo "💾 Uso memoria Apache: ${MEMORY_USAGE}%"
if (( $(echo "$MEMORY_USAGE < 80" | bc -l) )); then
    echo "✅ Memory Usage: OK"
else
    echo "⚠️  Memory Usage: ALTO (>80%)"
fi
echo ""

# Riepilogo
echo "📊 RIEPILOGO TEST"
echo "================="
echo "🌐 URL Applicazione: http://localhost:8080"
echo "🔍 Health Check: http://localhost:8080/api/v1/health"
echo "📊 API Events: http://localhost:8080/api/v1/events"
echo "📧 Test Email: http://localhost:8080/api/v1/test-email"
echo ""
echo "📝 Comandi utili:"
echo "   • Vedi log: docker-compose -f docker-compose.cloudrun.yml logs -f"
echo "   • Riavvia: docker-compose -f docker-compose.cloudrun.yml restart"
echo "   • Ferma: docker-compose -f docker-compose.cloudrun.yml down"
echo ""

# Test finale: Apri browser
echo "🌐 Aprendo browser per test manuale..."
if [[ "$OSTYPE" == "darwin"* ]]; then
    open http://localhost:8080
elif [[ "$OSTYPE" == "linux-gnu"* ]]; then
    xdg-open http://localhost:8080
else
    echo "Apri manualmente: http://localhost:8080"
fi
