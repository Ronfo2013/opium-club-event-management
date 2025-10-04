import PushNotification from 'react-native-push-notification';
import { Platform } from 'react-native';

// Configurazione notifiche push
export const initializeNotifications = () => {
  PushNotification.configure({
    // Funzione chiamata quando viene ricevuta una notifica
    onNotification: function(notification) {
      console.log('Notifica ricevuta:', notification);
      
      // Gestisce le notifiche in base al tipo
      if (notification.userInteraction) {
        // L'utente ha toccato la notifica
        handleNotificationPress(notification);
      }
    },

    // Funzione chiamata quando viene ricevuto un token
    onRegister: function(token) {
      console.log('Token notifiche:', token);
      // Invia il token al server
      sendTokenToServer(token);
    },

    // Permessi richiesti
    permissions: {
      alert: true,
      badge: true,
      sound: true,
    },

    // Popup iniziale
    popInitialNotification: true,

    // ID del canale (Android)
    requestPermissions: Platform.OS === 'ios',
  });

  // Crea il canale per Android
  if (Platform.OS === 'android') {
    PushNotification.createChannel(
      {
        channelId: 'opium-club-channel',
        channelName: 'Opium Club Notifications',
        channelDescription: 'Notifiche per eventi e aggiornamenti',
        playSound: true,
        soundName: 'default',
        importance: 4,
        vibrate: true,
      },
      (created) => console.log(`Canale creato: ${created}`)
    );
  }
};

// Invia il token al server
const sendTokenToServer = async (token) => {
  try {
    const response = await fetch('http://localhost:8000/api/mobile-token', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        token: token.token,
        platform: Platform.OS,
        timestamp: new Date().toISOString()
      })
    });

    if (response.ok) {
      console.log('Token inviato al server con successo');
    }
  } catch (error) {
    console.error('Errore invio token:', error);
  }
};

// Gestisce il tap su una notifica
const handleNotificationPress = (notification) => {
  const { data } = notification;
  
  if (data && data.type) {
    switch (data.type) {
      case 'event':
        // Naviga alla schermata dell'evento
        // navigation.navigate('EventDetail', { eventId: data.event_id });
        break;
      case 'registration':
        // Naviga alla schermata di registrazione
        // navigation.navigate('Registration', { eventId: data.event_id });
        break;
      case 'admin':
        // Naviga al pannello admin
        // navigation.navigate('Admin');
        break;
      default:
        // Naviga alla home
        // navigation.navigate('Home');
        break;
    }
  }
};

// Invia una notifica locale
export const sendLocalNotification = (title, message, data = {}) => {
  PushNotification.localNotification({
    channelId: 'opium-club-channel',
    title: title,
    message: message,
    data: data,
    playSound: true,
    soundName: 'default',
    vibrate: true,
    vibration: 300,
  });
};

// Programma una notifica
export const scheduleNotification = (title, message, date, data = {}) => {
  PushNotification.localNotificationSchedule({
    channelId: 'opium-club-channel',
    title: title,
    message: message,
    date: date,
    data: data,
    playSound: true,
    soundName: 'default',
    vibrate: true,
  });
};

// Cancella tutte le notifiche programmate
export const cancelAllNotifications = () => {
  PushNotification.cancelAllLocalNotifications();
};

// Ottiene le notifiche programmate
export const getScheduledNotifications = () => {
  return new Promise((resolve) => {
    PushNotification.getScheduledLocalNotifications((notifications) => {
      resolve(notifications);
    });
  });
};
