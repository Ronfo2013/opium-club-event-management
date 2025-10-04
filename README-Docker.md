# 🐳 Sviluppo con Docker - Guida Rapida

## Perché Docker?
- ✅ **Preview istantanea** delle modifiche (hot-reload)
- ✅ **No più deploy** su Google Cloud per testare
- ✅ **Ambiente identico** per tutti gli sviluppatori
- ✅ **Database locale** sempre disponibile
- ✅ **Zero configurazione** - tutto pronto in 2 minuti

## 🚀 Setup Immediato

### 1. Installa Docker
- **Mac**: [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- **Windows**: [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- **Linux**: `sudo apt install docker.io docker-compose`

### 2. Avvio Super Veloce (Un Solo Comando!)
```bash
# Script automatico - fa tutto per te!
./start-docker.sh
```

**Oppure manualmente:**
```bash
# 1. Copia configurazione
cp env.development .env

# 2. Avvia tutto
docker-compose up --build -d
```

### 3. Accedi all'app
- **App principale**: http://localhost:8090
- **Admin panel**: http://localhost:8090/admin
- **PHPMyAdmin**: http://localhost:8082 (user: root, password: docker_password)

### 🗄️ Database già Pronto!
Il database viene creato automaticamente con:
- **5 eventi di test** (Capodanno, Natale, Halloween, Winter Party, San Valentino)
- **3 utenti di prova** (Mario, Giulia, Luca)
- **Testi email preconfigurati** (subject, template, ecc.)
- **Tutte le tabelle reali** della tua applicazione:
  - `utenti` - Iscritti agli eventi
  - `events` - Eventi organizzati  
  - `email_texts` - Testi personalizzabili email
  - `birthday_templates` - Sistema compleanno
  - `birthday_sent` - Tracking auguri
  - `birthday_assets` - Immagini compleanno
  - `submissions` - Form submissions legacy

## 🔄 Workflow di Sviluppo

### Modifica e testa immediatamente:
1. **Modifica qualsiasi file** nel progetto
2. **Salva** (Ctrl+S / Cmd+S)
3. **Refresh browser** (F5)
4. **Vedi i cambiamenti istantaneamente!**

### Comandi utili:
```bash
# Vedi i log in tempo reale
docker-compose logs -f app

# Riavvia solo l'app (se necessario)
docker-compose restart app

# Accedi al container per debugging
docker-compose exec app bash

# Ferma tutto
docker-compose down

# Rimuovi tutto (database incluso) per ricominciare
docker-compose down -v
```

## 📁 Struttura dei Volumi

I tuoi file sono salvati in volumi Docker persistenti:
- `uploads/` - File caricati dagli utenti
- `qrcodes/` - QR codes generati
- `generated_images/` - Immagini generate
- `generated_pdfs/` - PDF generati
- Database MySQL - Dati persistenti

## 🐛 Debugging

### Vedi i log degli errori:
```bash
# Log dell'applicazione PHP
docker-compose logs app

# Log del database
docker-compose logs db

# Log in tempo reale
docker-compose logs -f
```

### Ispeziona il database:
- Usa PHPMyAdmin: http://localhost:8081
- O connettiti direttamente: `localhost:3307`

## ⚡ Vantaggi vs Produzione

| Aspetto | Docker Locale | Deploy GCloud |
|---------|---------------|---------------|
| **Tempo per vedere modifiche** | 1 secondo | 2-5 minuti |
| **Costo** | Gratis | € per deploy |
| **Debug** | Completo | Limitato |
| **Database** | Pieno controllo | Solo produzione |
| **Rischio** | Zero | Può rompere prod |

## 🚀 Deploy in Produzione

Quando tutto funziona in Docker:
```bash
# Testa tutto localmente
docker-compose up

# Quando sei soddisfatto, deploy normale su GCloud
gcloud app deploy
```

## 🔧 Personalizzazioni

### Cambiare porta dell'app:
Modifica `docker-compose.yml`:
```yaml
ports:
  - "9000:80"  # App su localhost:9000
```

### Aggiungere estensioni PHP:
Modifica `Dockerfile`:
```dockerfile
RUN docker-php-ext-install mysqli pdo pdo_mysql gd zip mbstring exif
```

### Database con dati reali:
```bash
# Copia dump da produzione nel container
docker-compose exec db mysql -u root -pdocker_password form_qrcode < backup.sql
```

---

**🎉 Ora puoi sviluppare velocemente senza deployare su Google Cloud ogni volta!**
