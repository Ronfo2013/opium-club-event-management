# Requisiti per il Sistema Opium Club Event Management

## 📋 Panoramica Sistema

Il sistema Opium Club Event Management è composto da:
- **Backend PHP Tradizionale** (PHP 8.2) - **SISTEMA PRINCIPALE**
- **Backend Laravel** (PHP 8.2) - *In sviluppo/migrazione*
- **Frontend React** (JavaScript/TypeScript)
- **App Mobile React Native** (JavaScript/TypeScript)
- **Database MySQL**
- **Storage File System**

---

## 🏗️ Architettura Sistema

### Componenti Principali
1. **Backend PHP Tradizionale**: Sistema principale in produzione
2. **Frontend React**: Interfaccia utente moderna
3. **App Mobile**: Applicazione mobile React Native
4. **Backend Laravel**: Versione moderna in sviluppo

---

## 🔧 Requisiti Tecnici

### 1. Runtime Environment

#### PHP 8.2
- **Versione**: PHP 8.2 (supportata da App Engine)
- **Estensioni richieste**:
  - `pdo_mysql`
  - `mysqli`
  - `gd` (per manipolazione immagini)
  - `zip`
  - `mbstring`
  - `curl`
  - `openssl`

#### Dipendenze PHP (composer.json)
```json
{
  "require": {
    "setasign/fpdf": "^1.8"
  }
}
```

#### Librerie PHP Tradizionali
- **FPDF**: Generazione PDF
- **PHPMailer**: Invio email
- **phpqrcode**: Generazione QR codes
- **GD Extension**: Manipolazione immagini
- **PDO MySQL**: Connessione database

### 2. Database Requirements

#### MySQL Database
- **Database**: `form_qrcode`
- **Utente**: `root`
- **Password**: Configurata tramite variabili ambiente
- **Connessione**: Standard MySQL

#### Tabelle Principali
- `events` - Gestione eventi
- `utenti` - Utenti registrati
- `email_texts` - Template email personalizzabili
- `birthday_templates` - Template compleanni
- `birthday_sent` - Tracking auguri inviati
- `birthday_assets` - Asset per sistema compleanni

### 3. Storage Requirements

#### File System Storage
- **Directory necessarie**:
  - `/public/uploads` - File caricati dagli utenti
  - `/public/qrcodes` - QR code generati
  - `/public/generated_images` - Immagini generate
  - `/public/generated_pdfs` - PDF generati
  - `/public/assets` - Asset statici

#### Permessi Storage
- Lettura/scrittura per l'applicazione
- Accesso pubblico per asset statici

### 4. Cache e Sessioni

#### Cache Sistema
- **Driver**: File system
- **Directory**: `/tmp/` o directory dedicata
- **Utilizzo**:
  - Cache applicazione
  - Sessioni utente
  - Cache temporanea

---

## 🌐 Configurazione Rete

### 1. Variabili Ambiente

#### Database
```env
DB_HOST="localhost"
DB_NAME="form_qrcode"
DB_USER="root"
DB_PASSWORD="[PASSWORD_SICURA]"
```

#### Email SMTP
```env
MAIL_MAILER="smtp"
MAIL_HOST="smtp.ionos.it"
MAIL_USERNAME="info@opiumpordenone.com"
MAIL_PASSWORD="[PASSWORD_EMAIL]"
MAIL_PORT="587"
MAIL_ENCRYPTION="tls"
```

#### Storage
```env
STORAGE_PATH="/var/www/html/public"
UPLOADS_PATH="/var/www/html/public/uploads"
QRCODES_PATH="/var/www/html/public/qrcodes"
```

#### Cache Sistema
```env
CACHE_DRIVER="file"
SESSION_DRIVER="file"
CACHE_PATH="/tmp/cache"
```

### 2. Sicurezza

#### Configurazione App
```env
APP_ENV="production"
APP_DEBUG="false"
APP_URL="http://localhost"
```

#### Autenticazione Admin
```env
ADMIN_PASSWORD="[PASSWORD_SICURA]"
```

---

## 📁 Struttura File Richiesta

### Directory Principali
```
/
├── app.yaml                 # Configurazione App Engine
├── composer.json            # Dipendenze PHP
├── composer.lock           # Lock dipendenze
├── public/                 # Document root (Backend PHP principale)
│   ├── index.php           # Entry point
│   ├── admin.php           # Pannello admin
│   ├── save_form.php       # Logica registrazione
│   ├── api/                # API endpoints
│   ├── assets/
│   ├── uploads/
│   ├── qrcodes/
│   ├── generated_images/
│   └── generated_pdfs/
├── src/                    # Codice PHP principale
│   ├── bootstrap.php       # Bootstrap locale
│   ├── bootstrap_gcloud.php # Bootstrap App Engine
│   ├── config/
│   ├── Controllers/
│   ├── Models/
│   └── lib/
├── lib/                    # Librerie PHP tradizionali
│   ├── fpdf/
│   ├── PHPMailer/
│   └── phpqrcode/
├── laravel-backend/        # Backend Laravel (in sviluppo)
└── vendor/                 # Dipendenze PHP
```

### File di Configurazione Necessari
- `composer.json` e `composer.lock`
- `src/bootstrap.php` (bootstrap locale)
- `src/config/config.php` (configurazione locale)
- `.htaccess` (configurazione Apache)

---

## 🚀 Processo di Installazione

### 1. Pre-Installazione Checklist

#### Database
- [ ] Server MySQL installato e configurato
- [ ] Database `form_qrcode` creato
- [ ] Tabelle create e popolate
- [ ] Dati iniziali inseriti

#### Storage
- [ ] Directory storage create
- [ ] Permessi directory configurati
- [ ] Directory necessarie create

#### Cache
- [ ] Directory cache configurata
- [ ] Permessi cache verificati
- [ ] Configurazione cache verificata

#### Sicurezza
- [ ] Password database sicure
- [ ] Password email sicure
- [ ] CORS configurato
- [ ] Variabili ambiente protette

### 2. Build Process

#### Backend PHP Tradizionale
```bash
# Installa dipendenze Composer
composer install --no-dev --optimize-autoloader

# Verifica struttura file
ls -la public/
ls -la src/
ls -la lib/

# Test connessione database (opzionale)
php public/test_bootstrap.php
```

#### Frontend React (se necessario)
```bash
cd react-frontend
npm install
npm run build
```

### 3. Installazione Commands

#### Installazione Completa
```bash
# Clona il repository
git clone <repository-url>
cd opium-club-event-management

# Installa dipendenze
composer install

# Configura permessi
chmod -R 755 public/
chmod -R 777 public/uploads public/qrcodes public/generated_images public/generated_pdfs

# Avvia servizi Docker
./start-docker.sh
```

---

## 📊 Monitoring e Health Checks

### 1. Health Check Endpoint
- **URL**: `/health.php`
- **Funzione**: Verifica stato sistema
- **Controlli**: Database, storage, cache

### 2. Logging
- **Error Logging**: `public/error_log.txt`
- **Application Logging**: `logs/` directory
- **Debug Mode**: Configurabile via variabili ambiente

### 3. Monitoring
- **File System**: Monitoraggio spazio disco
- **Database**: Monitoraggio connessioni
- **Performance**: Monitoraggio tempi risposta

---

## 🔐 Sicurezza e Compliance

### 1. Autenticazione
- **Sessioni PHP native** per autenticazione admin
- **Sessioni sicure** con file cache
- **Rate limiting** configurato

### 2. Validazione Input
- **Validazione PHP nativa** per tutti gli input
- **Sanitizzazione** dati utente con `filter_var()`
- **PDO Prepared Statements** per SQL injection protection

### 3. Email Security
- **TLS Encryption** per SMTP
- **Autenticazione** email sicura
- **Spam Protection** configurato

---

## 📱 Mobile App Integration

### 1. API Endpoints
- **Base URL**: `https://[PROJECT_ID].appspot.com/api/v1`
- **Autenticazione**: Bearer Token
- **Rate Limiting**: 1000 requests/hour per utente

### 2. Push Notifications
- **Firebase Cloud Messaging** (se implementato)
- **Background sync** per dati offline

---

## 🛠️ Troubleshooting

### 1. Problemi Comuni

#### Database Connection
```bash
# Verifica connessione MySQL
mysql -u root -p -h localhost form_qrcode
```

#### Storage Access
```bash
# Verifica permessi directory
ls -la public/uploads
ls -la public/qrcodes
```

#### Cache Sistema
```bash
# Verifica directory cache
ls -la /tmp/cache
```

### 2. Log Analysis
```bash
# Visualizza log applicazione
tail -f public/error_log.txt

# Visualizza log sistema
tail -f logs/laravel.log
```

### 3. Performance Monitoring
- **File System** monitoring
- **Database** query optimization
- **PHP** error reporting

---

## 💰 Costi Stimati

### 1. Server Hosting
- **VPS/Server**: $10-50/mese (dipende dal provider)
- **Bandwidth**: Incluso o a consumo

### 2. Database MySQL
- **MySQL Server**: Incluso nel hosting
- **Storage**: Incluso nel piano hosting

### 3. File Storage
- **Storage Locale**: Incluso nel server
- **Backup**: $5-20/mese per backup automatici

### 4. Cache Sistema
- **File Cache**: Gratuito (spazio disco)
- **Performance**: Ottimizzazione inclusa

---

## 📞 Supporto e Manutenzione

### 1. Team di Supporto
- **Email**: info@opiumpordenone.com
- **Sviluppatore**: Benhanced (www.benhanced.it)

### 2. Backup e Recovery
- **Database**: Backup automatici giornalieri
- **File System**: Backup incrementali
- **Storage**: Backup manuali o automatici

### 3. Updates e Patches
- **PHP**: Updates server hosting
- **Dependencies**: Monitoraggio vulnerabilità Composer
- **Librerie**: Updates manuali per FPDF, PHPMailer, phpqrcode

---

## ✅ Checklist Finale

### Pre-Installazione
- [ ] Tutte le dipendenze installate
- [ ] Database configurato e popolato
- [ ] Storage configurato con permessi
- [ ] Cache file system funzionante
- [ ] Variabili ambiente configurate
- [ ] Health checks implementati
- [ ] Sicurezza verificata

### Post-Installazione
- [ ] Installazione completata con successo
- [ ] Health checks passano
- [ ] Database connesso
- [ ] Email funzionante
- [ ] Storage accessibile
- [ ] Cache operativa
- [ ] Monitoring attivo
- [ ] Backup configurato

---

**Documento creato per Opium Club Event Management System**  
**Versione**: 1.0  
**Data**: $(date)  
**Autore**: Benhanced Development Team
