import { useState, useEffect } from 'react';
import toast from 'react-hot-toast';

export const usePWA = () => {
  const [isInstallable, setIsInstallable] = useState(false);
  const [isInstalled, setIsInstalled] = useState(false);
  const [deferredPrompt, setDeferredPrompt] = useState(null);
  const [isOnline, setIsOnline] = useState(navigator.onLine);
  const [isUpdateAvailable, setIsUpdateAvailable] = useState(false);

  useEffect(() => {
    // Controlla se l'app è già installata
    const checkIfInstalled = () => {
      if (window.matchMedia('(display-mode: standalone)').matches) {
        setIsInstalled(true);
      }
    };

    // Gestisce l'evento beforeinstallprompt
    const handleBeforeInstallPrompt = (e) => {
      e.preventDefault();
      setDeferredPrompt(e);
      setIsInstallable(true);
    };

    // Gestisce l'evento appinstalled
    const handleAppInstalled = () => {
      setIsInstalled(true);
      setIsInstallable(false);
      setDeferredPrompt(null);
      toast.success('App installata con successo!');
    };

    // Gestisce la connessione di rete
    const handleOnline = () => {
      setIsOnline(true);
      toast.success('Connessione ripristinata');
    };

    const handleOffline = () => {
      setIsOnline(false);
      toast.error('Connessione persa - Modalità offline');
    };

    // Registra il service worker
    const registerServiceWorker = async () => {
      if ('serviceWorker' in navigator) {
        try {
          const registration = await navigator.serviceWorker.register('/sw.js');
          console.log('Service Worker registrato:', registration);

          // Controlla aggiornamenti
          registration.addEventListener('updatefound', () => {
            const newWorker = registration.installing;
            newWorker.addEventListener('statechange', () => {
              if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                setIsUpdateAvailable(true);
                toast.success('Aggiornamento disponibile!', {
                  duration: 5000,
                  action: {
                    label: 'Aggiorna',
                    onClick: () => {
                      newWorker.postMessage({ type: 'SKIP_WAITING' });
                      window.location.reload();
                    }
                  }
                });
              }
            });
          });
        } catch (error) {
          console.error('Errore registrazione Service Worker:', error);
        }
      }
    };

    // Aggiunge gli event listeners
    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.addEventListener('appinstalled', handleAppInstalled);
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    // Inizializza
    checkIfInstalled();
    registerServiceWorker();

    // Cleanup
    return () => {
      window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
      window.removeEventListener('appinstalled', handleAppInstalled);
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  // Funzione per installare l'app
  const installApp = async () => {
    if (!deferredPrompt) return;

    try {
      deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      
      if (outcome === 'accepted') {
        console.log('Utente ha accettato l\'installazione');
      } else {
        console.log('Utente ha rifiutato l\'installazione');
      }
      
      setDeferredPrompt(null);
      setIsInstallable(false);
    } catch (error) {
      console.error('Errore durante l\'installazione:', error);
      toast.error('Errore durante l\'installazione dell\'app');
    }
  };

  // Funzione per richiedere permessi notifiche
  const requestNotificationPermission = async () => {
    if (!('Notification' in window)) {
      toast.error('Le notifiche non sono supportate da questo browser');
      return false;
    }

    if (Notification.permission === 'granted') {
      return true;
    }

    if (Notification.permission === 'denied') {
      toast.error('Le notifiche sono state disabilitate');
      return false;
    }

    try {
      const permission = await Notification.requestPermission();
      if (permission === 'granted') {
        toast.success('Notifiche abilitate!');
        return true;
      } else {
        toast.error('Permessi notifiche negati');
        return false;
      }
    } catch (error) {
      console.error('Errore richiesta permessi notifiche:', error);
      toast.error('Errore durante la richiesta dei permessi');
      return false;
    }
  };

  // Funzione per inviare notifica
  const sendNotification = (title, options = {}) => {
    if (Notification.permission === 'granted') {
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
  };

  return {
    isInstallable,
    isInstalled,
    isOnline,
    isUpdateAvailable,
    installApp,
    requestNotificationPermission,
    sendNotification
  };
};
