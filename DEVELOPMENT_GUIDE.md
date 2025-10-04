# 🛠️ Guida allo Sviluppo - Opium Club Event Management

## 🚀 Setup Iniziale

### 1. Avvia l'Ambiente di Sviluppo
```bash
# Clona il repository
git clone https://github.com/Ronfo2013/opium-club-event-management.git
cd opium-club-event-management

# Avvia tutto con Docker
./start-docker.sh
```

### 2. Verifica che Tutto Funzioni
- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000/api/v1/events
- **Database**: http://localhost:8080 (root/docker_password)
- **Email Testing**: http://localhost:8025

## 🔄 Workflow di Sviluppo

### Per Modifiche Frontend (React)
```bash
# Le modifiche sono automatiche con hot reload
# Modifica file in: react-frontend/src/
# Salva e vedi le modifiche su: http://localhost:3000
```

### Per Modifiche Backend (Laravel)
```bash
# Modifica file in: laravel-backend/app/
# Per vedere le modifiche:
docker-compose restart app

# Per modifiche al database:
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
```

### Per Modifiche Database
```bash
# Accedi al database
docker-compose exec mysql mysql -u root -p opium_events

# Oppure usa PHPMyAdmin: http://localhost:8080
```

## 🧪 Testing

### Test Frontend
```bash
cd react-frontend
npm test
```

### Test Backend
```bash
docker-compose exec app php artisan test
```

### Test API
```bash
# Test endpoint eventi
curl http://localhost:8000/api/v1/events

# Test registrazione utente
curl -X POST http://localhost:8000/api/v1/users/register \
  -H "Content-Type: application/json" \
  -d '{"first_name":"Test","last_name":"User","email":"test@example.com","phone":"1234567890","event_id":1}'
```

## 📦 Deploy di Test

### 1. Test Locale Completo
```bash
# Assicurati che tutto funzioni localmente
./start-docker.sh
# Testa tutte le funzionalità su localhost
```

### 2. Deploy su Staging (Google App Engine)
```bash
# Deploy su versione di test (non produzione)
gcloud app deploy --version=staging --no-promote
```

### 3. Deploy su Produzione
```bash
# Deploy finale
./deploy.sh
```

## 🔧 Comandi Utili

### Docker
```bash
# Vedi log in tempo reale
docker-compose logs -f

# Riavvia un servizio specifico
docker-compose restart app
docker-compose restart frontend
docker-compose restart mysql

# Entra nel container
docker-compose exec app bash
docker-compose exec frontend sh

# Ferma tutto
docker-compose down

# Ricostruisci tutto
docker-compose up --build -d
```

### Laravel
```bash
# Comandi dentro il container app
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan storage:link
```

### React
```bash
# Comandi dentro il container frontend
docker-compose exec frontend npm install
docker-compose exec frontend npm run build
docker-compose exec frontend npm test
```

## 🐛 Debug

### Log Laravel
```bash
docker-compose exec app tail -f storage/logs/laravel.log
```

### Log Nginx/PHP
```bash
docker-compose logs app
```

### Log React
```bash
docker-compose logs frontend
```

### Database
```bash
# Vedi tabelle
docker-compose exec mysql mysql -u root -p opium_events -e "SHOW TABLES;"

# Backup database
docker-compose exec mysql mysqldump -u root -p opium_events > backup.sql

# Restore database
docker-compose exec -i mysql mysql -u root -p opium_events < backup.sql
```

## 📱 Test Mobile

### App Expo
```bash
# Avvia Expo (se hai il container mobile)
docker-compose up mobile-dev

# Oppure manualmente:
cd mobile-app-expo
npm install
npx expo start
```

## 🚀 Deploy Process

### 1. Pre-Deploy Checklist
- [ ] Testato localmente con Docker
- [ ] Testate tutte le funzionalità principali
- [ ] Verificati i test automatici
- [ ] Controllati i log per errori
- [ ] Testata la generazione PDF
- [ ] Testato l'invio email
- [ ] Verificati i QR code

### 2. Deploy Staging
```bash
# Deploy su versione di test
gcloud app deploy --version=staging --no-promote
```

### 3. Test Staging
```bash
# Ottieni URL della versione staging
gcloud app versions list

# Testa su staging
curl https://staging-dot-premium-origin-471808-u0.appspot.com/api/v1/events
```

### 4. Deploy Produzione
```bash
# Deploy finale
./deploy.sh
```

## 🔒 Sicurezza

### Variabili Sensibili
- Mai committare file `.env` con password reali
- Usa variabili d'ambiente per produzione
- Configura Google Cloud Secret Manager per password

### Test di Sicurezza
```bash
# Test rate limiting
for i in {1..10}; do curl http://localhost:8000/api/v1/events; done

# Test validazione input
curl -X POST http://localhost:8000/api/v1/users/register \
  -H "Content-Type: application/json" \
  -d '{"invalid":"data"}'
```

## 📊 Monitoring

### Health Check
```bash
# Verifica stato applicazione
curl http://localhost:8000/api/v1/health

# Verifica database
docker-compose exec mysql mysqladmin -u root -p status

# Verifica Redis
docker-compose exec redis redis-cli ping
```

### Performance
```bash
# Monitor CPU/RAM
docker stats

# Log performance
docker-compose exec app php artisan telescope:install
```
