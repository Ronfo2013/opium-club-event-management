# 🎉 Opium Club Events - Laravel + React

Applicazione moderna per la gestione eventi con sistema QR Code, migrata da PHP tradizionale a Laravel (backend) + React (frontend).

## 🏗️ Architettura

### Backend (Laravel)
- **Framework**: Laravel 10
- **Database**: MySQL
- **Autenticazione**: Laravel Sanctum
- **Email**: SMTP con template personalizzati
- **QR Code**: Generazione automatica con PDF
- **API**: RESTful API con versioning

### Frontend (React)
- **Framework**: React 18
- **Styling**: Tailwind CSS
- **State Management**: React Query + Context API
- **Routing**: React Router DOM
- **Forms**: React Hook Form
- **Notifications**: React Hot Toast

## 🚀 Funzionalità Migrate

### ✅ Completate
- [x] Sistema di registrazione eventi
- [x] Generazione QR Code automatica
- [x] Invio email con PDF allegato
- [x] Pannello admin per gestione utenti
- [x] Validazione QR Code per ingressi
- [x] Sistema di autenticazione
- [x] API RESTful complete
- [x] Frontend React moderno

### 🔄 In Migrazione
- [ ] Sistema compleanni automatico
- [ ] Gestione template email
- [ ] Export dati
- [ ] Statistiche avanzate

## 📋 Prerequisiti

- Docker & Docker Compose
- Node.js 18+ (per sviluppo frontend)
- PHP 8.2+ (per sviluppo backend)

## 🛠️ Installazione

### 1. Clona il repository
```bash
git clone <repository-url>
cd opium-club-events
```

### 2. Avvia i servizi Docker
```bash
docker-compose up -d
```

### 3. Inizializza Laravel
```bash
./init-laravel.sh
```

### 4. Installa dipendenze React
```bash
cd react-frontend
npm install
npm start
```

## 🌐 Accesso all'Applicazione

- **Frontend React**: http://localhost:3000
- **Backend Laravel**: http://localhost:8000
- **API**: http://localhost:8000/api/v1
- **Admin Panel**: http://localhost:3000/admin
- **Database**: localhost:3306
- **Redis**: localhost:6379

## 🔐 Credenziali Admin

- **Email**: admin@opiumpordenone.com
- **Password**: admin123

## 📚 API Endpoints

### Pubblici
- `GET /api/v1/events` - Lista eventi
- `GET /api/v1/events/open` - Eventi aperti
- `POST /api/v1/users` - Registrazione utente
- `POST /api/v1/qr/validate` - Validazione QR Code
- `POST /api/v1/auth/login` - Login admin

### Protetti (richiedono autenticazione)
- `GET /api/v1/users` - Lista utenti
- `POST /api/v1/events` - Crea evento
- `PUT /api/v1/events/{id}` - Aggiorna evento
- `DELETE /api/v1/events/{id}` - Elimina evento
- `GET /api/v1/users/stats` - Statistiche utenti

## 🗄️ Database

### Tabelle Principali
- `events` - Eventi organizzati
- `users` - Utenti registrati
- `personal_access_tokens` - Token autenticazione
- `sessions` - Sessioni utente

### Migrazioni
```bash
cd laravel-backend
php artisan migrate
```

### Seeding
```bash
php artisan db:seed
```

## 📧 Sistema Email

### Template Disponibili
- **Welcome Email**: Invio automatico dopo registrazione
- **Birthday Email**: Invio automatico per compleanni
- **Custom Email**: Email personalizzate dall'admin

### Configurazione SMTP
Le credenziali SMTP sono configurate nel file `.env`:
```
MAIL_HOST=smtp.ionos.it
MAIL_PORT=587
MAIL_USERNAME=info@opiumpordenone.com
MAIL_PASSWORD=Camilla2020@
```

## 🔧 Sviluppo

### Backend (Laravel)
```bash
cd laravel-backend
composer install
php artisan serve
```

### Frontend (React)
```bash
cd react-frontend
npm install
npm start
```

### Testing
```bash
# Backend tests
cd laravel-backend
php artisan test

# Frontend tests
cd react-frontend
npm test
```

## 📦 Deploy

### Produzione
1. Configura variabili ambiente
2. Esegui migrazioni: `php artisan migrate --force`
3. Ottimizza cache: `php artisan optimize`
4. Build frontend: `npm run build`

### Docker
```bash
docker-compose -f docker-compose.prod.yml up -d
```

## 🐛 Troubleshooting

### Problemi Comuni

1. **Errore 500 Laravel**
   ```bash
   cd laravel-backend
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Frontend non si connette al backend**
   - Verifica che il backend sia in esecuzione su porta 8000
   - Controlla le variabili CORS in Laravel

3. **Email non inviate**
   - Verifica credenziali SMTP
   - Controlla log Laravel: `tail -f storage/logs/laravel.log`

## 📈 Migrazione da PHP Tradizionale

### Dati Migrati
- ✅ Struttura database
- ✅ Logica business
- ✅ Sistema QR Code
- ✅ Invio email
- ✅ Autenticazione admin

### File PHP Originali
I file PHP originali sono mantenuti in:
- `public/` (applicazione PHP tradizionale)
- `src/` (logica business PHP)

### Nuova Struttura
- `laravel-backend/` (nuovo backend Laravel)
- `react-frontend/` (nuovo frontend React)

## 🤝 Contributi

1. Fork del repository
2. Crea feature branch: `git checkout -b feature/nuova-funzionalita`
3. Commit modifiche: `git commit -am 'Aggiunge nuova funzionalità'`
4. Push branch: `git push origin feature/nuova-funzionalita`
5. Crea Pull Request

## 📄 Licenza

Questo progetto è proprietario di Opium Club Pordenone.

## 📞 Supporto

Per supporto tecnico:
- Email: info@opiumpordenone.com
- Telefono: [Inserisci numero]

---

**Opium Club Pordenone** - Sistema di gestione eventi moderno 🎉





