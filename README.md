# Opium Club Event Management System

Sistema di gestione eventi con generazione automatica di QR code per Opium Club Pordenone, sviluppato con Laravel + React.

## 🚀 Caratteristiche Principali

- **Registrazione Eventi**: Form di iscrizione con validazione completa
- **Generazione QR Code**: QR code personalizzati per ogni utente
- **Sistema Email**: Invio automatico di email con PDF allegato
- **Dashboard Admin**: Gestione completa eventi e utenti
- **Scanner QR**: Validazione QR code per l'accesso
- **Sistema Compleanni**: Invio automatico di auguri personalizzati
- **Statistiche**: Report dettagliati e analytics
- **Responsive Design**: Interfaccia ottimizzata per mobile e desktop

## 🏗️ Architettura

### Backend (Laravel 10)
- **API RESTful** con autenticazione Sanctum
- **Database MySQL** con migrazioni e seeders
- **Servizi dedicati** per QR code, PDF e email
- **Queue Jobs** per elaborazioni asincrone
- **Caching Redis** per performance ottimali

### Frontend (React 18)
- **Componenti modulari** con hooks personalizzati
- **React Query** per gestione stato server
- **Tailwind CSS** per styling moderno
- **React Router** per navigazione
- **Form validation** con React Hook Form

## 📋 Prerequisiti

- Docker e Docker Compose
- Node.js 18+ (per sviluppo locale)
- PHP 8.2+ (per sviluppo locale)
- Composer (per sviluppo locale)
- Google Cloud CLI (per deploy)

## 🐳 Avvio Rapido con Docker

### 1. Clona il repository
```bash
git clone <repository-url>
cd opium-club-event-management
```

### 2. Avvia l'ambiente Docker
```bash
./start-docker.sh
```

### 3. Accedi alle applicazioni
- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000
- **PHPMyAdmin**: http://localhost:8080
- **Mailhog**: http://localhost:8025

## 🛠️ Sviluppo Locale

### Backend Laravel
```bash
cd laravel-backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Frontend React
```bash
cd react-frontend
npm install
npm start
```

## 📁 Struttura Progetto

```
├── laravel-backend/          # Backend Laravel
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Mail/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   └── routes/api.php
├── react-frontend/           # Frontend React
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── hooks/
│   │   └── services/
│   └── public/
├── docker-compose.yml        # Configurazione Docker
├── app.yaml                 # Configurazione App Engine
└── deploy.sh                # Script di deploy
```

## 🔧 Configurazione

### Variabili Ambiente

#### Backend (.env)
```env
APP_NAME="Opium Club Event Management"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=opium_events
DB_USERNAME=root
DB_PASSWORD=docker_password

MAIL_MAILER=smtp
MAIL_HOST=smtp.ionos.it
MAIL_USERNAME=info@opiumpordenone.com
MAIL_PASSWORD=Camilla2020@

REDIS_HOST=redis
REDIS_PORT=6379
```

#### Frontend (.env)
```env
REACT_APP_API_URL=http://localhost:8000/api/v1
```

## 🚀 Deploy su Google App Engine

### 1. Configura Google Cloud
```bash
gcloud auth login
gcloud config set project premium-origin-471808-u0
```

### 2. Deploy automatico
```bash
./deploy.sh
```

### 3. Deploy manuale
```bash
cd laravel-backend
gcloud app deploy
```

## 📊 API Endpoints

### Pubblici
- `GET /api/v1/events` - Lista eventi
- `POST /api/v1/users/register` - Registrazione utente
- `GET /api/v1/qr/validate/{token}` - Validazione QR code

### Amministrativi (Autenticati)
- `GET /api/v1/admin/stats` - Statistiche dashboard
- `GET /api/v1/users` - Lista utenti
- `POST /api/v1/events` - Crea evento
- `PUT /api/v1/events/{id}` - Aggiorna evento
- `DELETE /api/v1/events/{id}` - Elimina evento

## 🗄️ Database

### Tabelle Principali
- `events` - Eventi organizzati
- `users` - Utenti registrati
- `email_texts` - Testi personalizzabili email
- `birthday_templates` - Template compleanni
- `birthday_sent` - Tracking auguri inviati

### Migrazioni
```bash
php artisan migrate
php artisan db:seed
```

## 🧪 Testing

### Backend
```bash
cd laravel-backend
php artisan test
```

### Frontend
```bash
cd react-frontend
npm test
```

## 📝 Comandi Utili

### Docker
```bash
# Avvia ambiente
./start-docker.sh

# Ferma ambiente
./stop-docker.sh

# Vedi log
docker-compose logs -f

# Riavvia servizio
docker-compose restart app
```

### Laravel
```bash
# Genera chiave
php artisan key:generate

# Esegui migrazioni
php artisan migrate

# Esegui seeders
php artisan db:seed

# Crea link storage
php artisan storage:link

# Pulisci cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### React
```bash
# Installa dipendenze
npm install

# Avvia sviluppo
npm start

# Build produzione
npm run build

# Test
npm test
```

## 🔒 Sicurezza

- **Autenticazione Sanctum** per API
- **Validazione input** rigorosa
- **Rate limiting** per protezione
- **CORS** configurato
- **Sanitizzazione** dati utente
- **Token CSRF** per form

## 📈 Performance

- **Caching Redis** per sessioni e cache
- **Lazy loading** componenti React
- **Code splitting** automatico
- **CDN** per asset statici
- **Database indexing** ottimizzato
- **Queue jobs** per operazioni pesanti

## 🐛 Troubleshooting

### Problemi Comuni

1. **Errore connessione database**
   ```bash
   docker-compose restart mysql
   ```

2. **Permessi file Laravel**
   ```bash
   docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
   ```

3. **Cache React non aggiornata**
   ```bash
   docker-compose restart frontend
   ```

4. **Email non inviate**
   - Verifica configurazione SMTP
   - Controlla log Mailhog

## 📞 Supporto

Per supporto tecnico:
- **Email**: info@opiumpordenone.com
- **Sviluppatore**: Benhanced (www.benhanced.it)

## 📄 Licenza

Questo progetto è proprietario di Opium Club Pordenone.

---

**Sviluppato con ❤️ da Benhanced per Opium Club Pordenone**






