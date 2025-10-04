# 🚀 Guida Testing Cloud Run - Opium Club Event Management

## 🎯 Obiettivo
Testare l'applicazione in un ambiente che simula esattamente Google Cloud Run per evitare errori durante il deploy reale.

## 🛠️ Setup Ambiente Cloud Run Simulato

### 1. Avvia il Simulatore Cloud Run
```bash
# Avvia ambiente Cloud Run simulato
docker-compose -f docker-compose.cloudrun.yml up --build -d

# Verifica che sia attivo
docker ps | grep opium-cloudrun
```

### 2. Testa l'Ambiente
```bash
# Esegui test automatici completi
./test-cloudrun.sh
```

## 🌐 URL di Test

- **Applicazione**: http://localhost:8080
- **Health Check**: http://localhost:8080/api/v1/health
- **API Events**: http://localhost:8080/api/v1/events
- **Admin Panel**: http://localhost:8080/admin

## 🔍 Test Manuali

### Test 1: Health Check
```bash
curl http://localhost:8080/api/v1/health
# Risultato atteso: {"status":"ok","timestamp":"..."}
```

### Test 2: Lista Eventi
```bash
curl http://localhost:8080/api/v1/events
# Risultato atteso: JSON con lista eventi
```

### Test 3: Registrazione Utente
```bash
curl -X POST http://localhost:8080/api/v1/users/register \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Test",
    "last_name": "User", 
    "email": "test@example.com",
    "phone": "1234567890",
    "event_id": 1
  }'
```

### Test 4: Generazione QR Code
```bash
curl -X POST http://localhost:8080/api/v1/qr/generate \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "event_id": 1}'
```

### Test 5: Invio Email
```bash
curl -X POST http://localhost:8080/api/v1/email/send \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "template": "welcome"}'
```

## 🐛 Debug e Troubleshooting

### Vedi Log in Tempo Reale
```bash
# Log dell'applicazione
docker-compose -f docker-compose.cloudrun.yml logs -f cloudrun-simulator

# Log del database
docker-compose -f docker-compose.cloudrun.yml logs -f cloudsql-mysql

# Log di Redis
docker-compose -f docker-compose.cloudrun.yml logs -f cloudsql-redis
```

### Entra nel Container
```bash
# Entra nel container principale
docker exec -it opium-cloudrun-sim bash

# Controlla configurazione Laravel
cd /var/www/html/laravel-backend
php artisan config:show
php artisan route:list
```

### Verifica Database
```bash
# Accedi al database
docker exec -it opium-cloudsql-mysql mysql -u root -p opium_events

# Comandi SQL utili:
SHOW TABLES;
SELECT * FROM events;
SELECT * FROM users;
```

### Verifica Redis
```bash
# Accedi a Redis
docker exec -it opium-cloudsql-redis redis-cli

# Comandi Redis utili:
PING
KEYS *
INFO memory
```

## ⚡ Test di Performance

### Test Carico
```bash
# Installa Apache Bench se non presente
# macOS: brew install httpd
# Ubuntu: sudo apt install apache2-utils

# Test 100 richieste con 10 connessioni concorrenti
ab -n 100 -c 10 http://localhost:8080/api/v1/health

# Test endpoint più pesante
ab -n 50 -c 5 http://localhost:8080/api/v1/events
```

### Monitor Risorse
```bash
# Monitor CPU/RAM
docker stats opium-cloudrun-sim

# Monitor specifico
docker exec opium-cloudrun-sim top
```

## 🔒 Test di Sicurezza

### Test Rate Limiting
```bash
# Testa rate limiting (se configurato)
for i in {1..20}; do
  curl -w "%{http_code}\n" -o /dev/null -s http://localhost:8080/api/v1/events
done
```

### Test Validazione Input
```bash
# Test input malformato
curl -X POST http://localhost:8080/api/v1/users/register \
  -H "Content-Type: application/json" \
  -d '{"invalid": "data"}'

# Test SQL Injection (dovrebbe essere bloccato)
curl "http://localhost:8080/api/v1/events?id=1'; DROP TABLE users; --"
```

### Test Headers di Sicurezza
```bash
# Verifica headers di sicurezza
curl -I http://localhost:8080/api/v1/events

# Dovresti vedere:
# X-Content-Type-Options: nosniff
# X-Frame-Options: DENY
# X-XSS-Protection: 1; mode=block
```

## 📊 Test Funzionalità Specifiche

### Test Sistema QR Code
```bash
# Genera QR code
curl -X POST http://localhost:8080/api/v1/qr/generate \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "event_id": 1}' \
  -o qr_test.png

# Verifica che il file sia stato creato
ls -la qr_test.png
```

### Test Generazione PDF
```bash
# Genera PDF
curl -X POST http://localhost:8080/api/v1/pdf/generate \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "event_id": 1}' \
  -o ticket_test.pdf

# Verifica PDF
file ticket_test.pdf
```

### Test Sistema Email
```bash
# Test invio email (dovrebbe funzionare con Mailhog locale)
curl -X POST http://localhost:8080/api/v1/email/send \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "template": "welcome"}'

# Verifica su Mailhog: http://localhost:8025
```

## 🚀 Deploy Test

### Test Build Docker
```bash
# Testa che il Dockerfile funzioni
docker build -f Dockerfile.cloudrun -t opium-cloudrun-test .

# Testa che il container si avvii
docker run -d -p 8080:8080 --name test-container opium-cloudrun-test

# Testa health check
sleep 30
curl http://localhost:8080/api/v1/health

# Pulisci
docker stop test-container && docker rm test-container
```

### Simula Deploy Cloud Run
```bash
# Testa configurazione app.yaml
gcloud app deploy --dry-run

# Testa deploy locale
gcloud app deploy app.yaml --no-promote --version=test-$(date +%s)
```

## 📋 Checklist Pre-Deploy

Prima di fare il deploy reale su Cloud Run, verifica:

- [ ] ✅ Health check risponde correttamente
- [ ] ✅ API endpoints funzionano
- [ ] ✅ Database connessione OK
- [ ] ✅ Redis connessione OK
- [ ] ✅ File permissions corrette
- [ ] ✅ Frontend build incluso
- [ ] ✅ Laravel cache configurato
- [ ] ✅ Performance accettabile (<1s response time)
- [ ] ✅ Memory usage sotto controllo (<80%)
- [ ] ✅ Static files serviti correttamente
- [ ] ✅ Headers di sicurezza presenti
- [ ] ✅ Rate limiting funziona
- [ ] ✅ QR code generation funziona
- [ ] ✅ PDF generation funziona
- [ ] ✅ Email system funziona
- [ ] ✅ Logs sono leggibili
- [ ] ✅ Error handling funziona
- [ ] ✅ Database migrations OK
- [ ] ✅ Seeders eseguiti correttamente

## 🔧 Comandi di Manutenzione

### Reset Ambiente
```bash
# Ferma tutto
docker-compose -f docker-compose.cloudrun.yml down

# Pulisci volumi (ATTENZIONE: cancella dati!)
docker-compose -f docker-compose.cloudrun.yml down -v

# Ricostruisci tutto
docker-compose -f docker-compose.cloudrun.yml up --build -d
```

### Backup Database
```bash
# Backup database
docker exec opium-cloudsql-mysql mysqldump -u root -p opium_events > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Aggiorna Codice
```bash
# Dopo aver fatto modifiche al codice
docker-compose -f docker-compose.cloudrun.yml restart cloudrun-simulator

# Per ricostruire completamente
docker-compose -f docker-compose.cloudrun.yml up --build -d cloudrun-simulator
```

## 🎯 Risultati Attesi

Dopo aver completato tutti i test, dovresti avere:

1. **Applicazione funzionante** su http://localhost:8080
2. **Tutti i test automatici** che passano
3. **Performance accettabili** (<1s response time)
4. **Sicurezza verificata** (headers, validazione, rate limiting)
5. **Funzionalità complete** (QR, PDF, Email)
6. **Log puliti** senza errori critici

Se tutto passa, sei pronto per il deploy su Cloud Run! 🚀
