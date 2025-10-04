// Servizio per gestire le push notifications
class NotificationService {
  constructor() {
    this.isSupported = 'Notification' in window && 'serviceWorker' in navigator;
    this.permission = Notification.permission;
    this.subscription = null;
  }

  // Richiede i permessi per le notifiche
  async requestPermission() {
    if (!this.isSupported) {
      throw new Error('Le notifiche non sono supportate');
    }

    if (this.permission === 'granted') {
      return true;
    }

    if (this.permission === 'denied') {
      throw new Error('Le notifiche sono state disabilitate');
    }

    try {
      this.permission = await Notification.requestPermission();
      return this.permission === 'granted';
    } catch (error) {
      throw new Error('Errore durante la richiesta dei permessi');
    }
  }

  // Sottoscrive l'utente alle notifiche push
  async subscribe() {
    if (!this.isSupported || this.permission !== 'granted') {
      throw new Error('Permessi notifiche non disponibili');
    }

    try {
      const registration = await navigator.serviceWorker.ready;
      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: this.urlBase64ToUint8Array(
          'BEl62iUYgUivxIkv69yViEuiBIa40HI0lF5AwyK3rS8'
        )
      });

      this.subscription = subscription;
      
      // Invia la sottoscrizione al server
      await this.sendSubscriptionToServer(subscription);
      
      return subscription;
    } catch (error) {
      throw new Error('Errore durante la sottoscrizione');
    }
  }

  // Invia la sottoscrizione al server
  async sendSubscriptionToServer(subscription) {
    try {
      const response = await fetch('/api/notifications/subscribe', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          subscription: subscription,
          user_agent: navigator.userAgent,
          timestamp: new Date().toISOString()
        })
      });

      if (!response.ok) {
        throw new Error('Errore invio sottoscrizione');
      }
    } catch (error) {
      console.error('Errore invio sottoscrizione:', error);
    }
  }

  // Annulla la sottoscrizione
  async unsubscribe() {
    if (!this.subscription) {
      return;
    }

    try {
      await this.subscription.unsubscribe();
      this.subscription = null;
      
      // Notifica il server
      await fetch('/api/notifications/unsubscribe', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          endpoint: this.subscription?.endpoint
        })
      });
    } catch (error) {
      console.error('Errore annullamento sottoscrizione:', error);
    }
  }

  // Invia una notifica locale
  sendLocalNotification(title, options = {}) {
    if (this.permission !== 'granted') {
      return;
    }

    const notification = new Notification(title, {
      icon: '/logo192.png',
      badge: '/logo192.png',
      ...options
    });

    notification.onclick = () => {
      window.focus();
      notification.close();
    };

    return notification;
  }

  // Converte la chiave VAPID
  urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
      .replace(/-/g, '+')
      .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  // Controlla se l'utente è sottoscritto
  async isSubscribed() {
    if (!this.isSupported) {
      return false;
    }

    try {
      const registration = await navigator.serviceWorker.ready;
      const subscription = await registration.pushManager.getSubscription();
      return subscription !== null;
    } catch (error) {
      return false;
    }
  }
}

export default new NotificationService();
