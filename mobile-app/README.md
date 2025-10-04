# 📱 Opium Club Mobile App

App mobile React Native per il sistema di gestione eventi Opium Club.

## 🚀 Caratteristiche

- **Scanner QR Code** per registrazioni e accessi
- **Gestione Eventi** con visualizzazione e registrazione
- **Pannello Admin** per gestione completa
- **Notifiche Push** per aggiornamenti e promemoria
- **Analytics** per tracking utilizzo
- **Design Moderno** con Material Design

## 📋 Prerequisiti

- Node.js 18+
- React Native CLI
- Android Studio (per Android)
- Xcode (per iOS)
- Java 11+

## 🛠️ Installazione

### 1. Installa le dipendenze
```bash
cd mobile-app
npm install
```

### 2. Configura Android
```bash
# Installa Android SDK
# Configura ANDROID_HOME nel tuo .bashrc/.zshrc
export ANDROID_HOME=$HOME/Android/Sdk
export PATH=$PATH:$ANDROID_HOME/emulator
export PATH=$PATH:$ANDROID_HOME/tools
export PATH=$PATH:$ANDROID_HOME/tools/bin
export PATH=$PATH:$ANDROID_HOME/platform-tools
```

### 3. Configura iOS
```bash
# Installa CocoaPods
sudo gem install cocoapods

# Installa le dipendenze iOS
cd ios && pod install && cd ..
```

## 🚀 Avvio

### Sviluppo
```bash
# Avvia Metro bundler
npm start

# Avvia su Android
npm run android

# Avvia su iOS
npm run ios
```

### Docker
```bash
# Avvia il container di sviluppo
docker-compose up mobile-dev
```

## 📱 Funzionalità

### Scanner QR Code
- Scansione QR code per eventi
- Conferma registrazioni
- Accesso rapido alle funzionalità

### Gestione Eventi
- Visualizzazione lista eventi
- Dettagli evento completi
- Registrazione con form integrato
- Calendario eventi

### Pannello Admin
- Dashboard con statistiche
- Gestione eventi (CRUD)
- Gestione utenti
- Analytics e report

### Notifiche Push
- Notifiche per nuovi eventi
- Promemoria eventi
- Aggiornamenti sistema
- Notifiche personalizzate

## 🔧 Configurazione

### API Endpoint
Configura l'URL del backend in `src/config/api.js`:
```javascript
export const API_BASE_URL = 'http://localhost:8000/api';
```

### Notifiche Push
Configura le chiavi VAPID in `src/services/notifications.js`:
```javascript
const VAPID_PUBLIC_KEY = 'your-vapid-public-key';
```

## 📊 Analytics

L'app traccia automaticamente:
- Visualizzazioni schermate
- Eventi utente
- Performance
- Errori
- Utilizzo funzionalità

## 🔒 Sicurezza

- Autenticazione sicura
- Token JWT
- Crittografia dati sensibili
- Validazione input
- Protezione API

## 🧪 Testing

```bash
# Test unitari
npm test

# Test E2E
npm run test:e2e

# Linting
npm run lint
```

## 📦 Build

### Android
```bash
# Build debug
npm run build:android:debug

# Build release
npm run build:android:release
```

### iOS
```bash
# Build debug
npm run build:ios:debug

# Build release
npm run build:ios:release
```

## 🚀 Deploy

### Google Play Store
1. Genera APK/AAB firmato
2. Carica su Google Play Console
3. Configura metadati e screenshot
4. Pubblica

### Apple App Store
1. Genera IPA
2. Carica su App Store Connect
3. Configura metadati
4. Invia per revisione

## 📱 Screenshots

- Home con eventi recenti
- Scanner QR code
- Dettaglio evento
- Pannello admin
- Notifiche push

## 🔄 Aggiornamenti

L'app supporta aggiornamenti OTA tramite:
- CodePush (Microsoft)
- Expo Updates
- Aggiornamenti nativi

## 📞 Supporto

Per problemi o domande:
- Email: info@opiumpordenone.com
- GitHub Issues
- Documentazione API

## 📄 Licenza

Proprietario - Opium Club Pordenone
