import DeviceInfo from 'react-native-device-info';
import AsyncStorage from '@react-native-async-storage/async-storage';

class MobileAnalytics {
  constructor() {
    this.sessionId = null;
    this.userId = null;
    this.startTime = Date.now();
    this.events = [];
    this.pageViews = [];
    this.isEnabled = __DEV__ ? false : true; // Disabilita in sviluppo
  }

  // Inizializza l'analytics
  async init() {
    if (!this.isEnabled) {
      console.log('Analytics mobile disabilitato in modalità sviluppo');
      return;
    }

    this.sessionId = await this.getOrCreateSessionId();
    this.userId = await this.getOrCreateUserId();

    // Traccia l'avvio dell'app
    this.trackEvent('app_start', {
      session_id: this.sessionId,
      user_id: this.userId,
      timestamp: new Date().toISOString(),
      device_info: await this.getDeviceInfo(),
      app_version: await DeviceInfo.getVersion(),
      build_number: await DeviceInfo.getBuildNumber(),
    });
  }

  // Ottiene o crea un ID sessione
  async getOrCreateSessionId() {
    let sessionId = await AsyncStorage.getItem('analytics_session_id');
    if (!sessionId) {
      sessionId = 'mobile_session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
      await AsyncStorage.setItem('analytics_session_id', sessionId);
    }
    return sessionId;
  }

  // Ottiene o crea un ID utente
  async getOrCreateUserId() {
    let userId = await AsyncStorage.getItem('analytics_user_id');
    if (!userId) {
      userId = 'mobile_user_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
      await AsyncStorage.setItem('analytics_user_id', userId);
    }
    return userId;
  }

  // Ottiene le informazioni del dispositivo
  async getDeviceInfo() {
    return {
      brand: await DeviceInfo.getBrand(),
      model: await DeviceInfo.getModel(),
      system_name: await DeviceInfo.getSystemName(),
      system_version: await DeviceInfo.getSystemVersion(),
      device_id: await DeviceInfo.getUniqueId(),
      is_emulator: await DeviceInfo.isEmulator(),
      has_notch: await DeviceInfo.hasNotch(),
      screen_width: await DeviceInfo.getScreenWidth(),
      screen_height: await DeviceInfo.getScreenHeight(),
    };
  }

  // Traccia un evento
  trackEvent(eventName, properties = {}) {
    const event = {
      event_name: eventName,
      properties: {
        ...properties,
        session_id: this.sessionId,
        user_id: this.userId,
        timestamp: new Date().toISOString(),
        platform: 'mobile',
      }
    };

    this.events.push(event);
    
    if (!this.isEnabled) {
      console.log('Mobile Analytics Event:', event);
    }
  }

  // Traccia una visualizzazione di schermata
  trackScreenView(screenName, properties = {}) {
    const screenView = {
      screen_name: screenName,
      session_id: this.sessionId,
      user_id: this.userId,
      timestamp: new Date().toISOString(),
      platform: 'mobile',
      ...properties
    };

    this.pageViews.push(screenView);
    
    if (!this.isEnabled) {
      console.log('Mobile Analytics Screen View:', screenView);
    }
  }

  // Traccia eventi specifici dell'app
  trackUserRegistration(userData) {
    this.trackEvent('mobile_user_registration', {
      email: userData.email,
      name: userData.name,
      registration_method: 'mobile_app'
    });
  }

  trackEventRegistration(eventData) {
    this.trackEvent('mobile_event_registration', {
      event_id: eventData.event_id,
      event_name: eventData.event_name,
      user_email: eventData.user_email,
      registration_method: 'mobile_app'
    });
  }

  trackQRCodeScan(qrData) {
    this.trackEvent('mobile_qr_code_scan', {
      qr_data: qrData,
      scan_location: 'mobile_scanner'
    });
  }

  trackAdminAction(action, details = {}) {
    this.trackEvent('mobile_admin_action', {
      action: action,
      ...details,
      admin_user: this.userId
    });
  }

  trackNotificationReceived(notification) {
    this.trackEvent('mobile_notification_received', {
      notification_id: notification.id,
      notification_type: notification.type,
      notification_title: notification.title
    });
  }

  trackNotificationOpened(notification) {
    this.trackEvent('mobile_notification_opened', {
      notification_id: notification.id,
      notification_type: notification.type,
      notification_title: notification.title
    });
  }

  trackAppBackground() {
    this.trackEvent('mobile_app_background', {
      session_duration: Date.now() - this.startTime
    });
  }

  trackAppForeground() {
    this.trackEvent('mobile_app_foreground', {
      session_duration: Date.now() - this.startTime
    });
  }

  // Invia i dati al server
  async flush() {
    if (this.events.length === 0 && this.pageViews.length === 0) {
      return;
    }

    const data = {
      session_id: this.sessionId,
      user_id: this.userId,
      events: [...this.events],
      page_views: [...this.pageViews],
      timestamp: new Date().toISOString(),
      platform: 'mobile'
    };

    try {
      const response = await fetch('http://localhost:8000/api/analytics', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
      });

      if (response.ok) {
        // Pulisce i dati inviati
        this.events = [];
        this.pageViews = [];
      }
    } catch (error) {
      console.error('Errore invio analytics mobile:', error);
    }
  }

  // Ottiene statistiche della sessione
  getSessionStats() {
    return {
      session_id: this.sessionId,
      user_id: this.userId,
      session_duration: Date.now() - this.startTime,
      events_count: this.events.length,
      page_views_count: this.pageViews.length,
      is_enabled: this.isEnabled,
      platform: 'mobile'
    };
  }
}

// Crea un'istanza singleton
const mobileAnalytics = new MobileAnalytics();

export default mobileAnalytics;
