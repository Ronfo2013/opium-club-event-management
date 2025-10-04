// Servizio Analytics per tracking utenti e eventi
class AnalyticsService {
  constructor() {
    this.isEnabled = process.env.NODE_ENV === 'production';
    this.sessionId = this.generateSessionId();
    this.userId = this.getUserId();
    this.startTime = Date.now();
    this.events = [];
    this.pageViews = [];
    
    // Inizializza il tracking
    this.init();
  }

  // Genera un ID sessione univoco
  generateSessionId() {
    return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
  }

  // Ottiene o crea un ID utente
  getUserId() {
    let userId = localStorage.getItem('analytics_user_id');
    if (!userId) {
      userId = 'user_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
      localStorage.setItem('analytics_user_id', userId);
    }
    return userId;
  }

  // Inizializza il servizio analytics
  init() {
    if (!this.isEnabled) {
      console.log('Analytics disabilitato in modalità sviluppo');
      return;
    }

    // Traccia la sessione
    this.trackEvent('session_start', {
      session_id: this.sessionId,
      user_id: this.userId,
      timestamp: new Date().toISOString(),
      user_agent: navigator.userAgent,
      screen_resolution: `${window.screen.width}x${window.screen.height}`,
      viewport_size: `${window.innerWidth}x${window.innerHeight}`,
      language: navigator.language,
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
    });

    // Traccia la pagina iniziale
    this.trackPageView(window.location.pathname);

    // Aggiunge listener per eventi di navigazione
    this.setupNavigationTracking();
    
    // Aggiunge listener per eventi di interazione
    this.setupInteractionTracking();

    // Aggiunge listener per eventi di performance
    this.setupPerformanceTracking();

    // Invia dati periodicamente
    this.setupPeriodicFlush();
  }

  // Configura il tracking della navigazione
  setupNavigationTracking() {
    // Traccia i cambi di pagina
    let currentPath = window.location.pathname;
    
    const trackNavigation = () => {
      if (window.location.pathname !== currentPath) {
        this.trackPageView(window.location.pathname);
        currentPath = window.location.pathname;
      }
    };

    // Usa popstate per SPA
    window.addEventListener('popstate', trackNavigation);
    
    // Override pushState e replaceState per React Router
    const originalPushState = window.history.pushState;
    const originalReplaceState = window.history.replaceState;
    
    window.history.pushState = function() {
      originalPushState.apply(window.history, arguments);
      setTimeout(trackNavigation, 0);
    };
    
    window.history.replaceState = function() {
      originalReplaceState.apply(window.history, arguments);
      setTimeout(trackNavigation, 0);
    };
  }

  // Configura il tracking delle interazioni
  setupInteractionTracking() {
    // Traccia i click sui pulsanti
    document.addEventListener('click', (event) => {
      const element = event.target.closest('button, a, [role="button"]');
      if (element) {
        this.trackEvent('click', {
          element_type: element.tagName.toLowerCase(),
          element_text: element.textContent?.trim() || '',
          element_id: element.id || '',
          element_class: element.className || '',
          href: element.href || '',
          page: window.location.pathname
        });
      }
    });

    // Traccia i form submission
    document.addEventListener('submit', (event) => {
      this.trackEvent('form_submit', {
        form_id: event.target.id || '',
        form_class: event.target.className || '',
        page: window.location.pathname
      });
    });

    // Traccia i focus sui campi input
    document.addEventListener('focus', (event) => {
      if (event.target.matches('input, textarea, select')) {
        this.trackEvent('form_field_focus', {
          field_type: event.target.type || event.target.tagName.toLowerCase(),
          field_name: event.target.name || '',
          field_id: event.target.id || '',
          page: window.location.pathname
        });
      }
    }, true);
  }

  // Configura il tracking delle performance
  setupPerformanceTracking() {
    // Traccia le performance di caricamento
    window.addEventListener('load', () => {
      setTimeout(() => {
        const navigation = performance.getEntriesByType('navigation')[0];
        if (navigation) {
          this.trackEvent('page_performance', {
            page: window.location.pathname,
            load_time: navigation.loadEventEnd - navigation.loadEventStart,
            dom_content_loaded: navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart,
            first_paint: this.getFirstPaint(),
            first_contentful_paint: this.getFirstContentfulPaint(),
            largest_contentful_paint: this.getLargestContentfulPaint()
          });
        }
      }, 1000);
    });
  }

  // Configura l'invio periodico dei dati
  setupPeriodicFlush() {
    // Invia dati ogni 30 secondi
    setInterval(() => {
      this.flush();
    }, 30000);

    // Invia dati quando la pagina viene chiusa
    window.addEventListener('beforeunload', () => {
      this.trackEvent('session_end', {
        session_duration: Date.now() - this.startTime,
        events_count: this.events.length,
        page_views_count: this.pageViews.length
      });
      this.flush(true); // Invia immediatamente
    });
  }

  // Traccia un evento personalizzato
  trackEvent(eventName, properties = {}) {
    const event = {
      event_name: eventName,
      properties: {
        ...properties,
        session_id: this.sessionId,
        user_id: this.userId,
        timestamp: new Date().toISOString(),
        page: window.location.pathname,
        referrer: document.referrer
      }
    };

    this.events.push(event);
    
    if (!this.isEnabled) {
      console.log('Analytics Event:', event);
    }
  }

  // Traccia una visualizzazione di pagina
  trackPageView(path) {
    const pageView = {
      page: path,
      session_id: this.sessionId,
      user_id: this.userId,
      timestamp: new Date().toISOString(),
      referrer: document.referrer,
      title: document.title
    };

    this.pageViews.push(pageView);
    
    if (!this.isEnabled) {
      console.log('Analytics Page View:', pageView);
    }
  }

  // Traccia eventi specifici dell'applicazione
  trackUserRegistration(userData) {
    this.trackEvent('user_registration', {
      email: userData.email,
      name: userData.name,
      event_type: 'registration'
    });
  }

  trackEventRegistration(eventData) {
    this.trackEvent('event_registration', {
      event_id: eventData.event_id,
      event_name: eventData.event_name,
      user_email: eventData.user_email,
      registration_method: eventData.method || 'web'
    });
  }

  trackAdminAction(action, details = {}) {
    this.trackEvent('admin_action', {
      action: action,
      ...details,
      admin_user: this.userId
    });
  }

  trackQRCodeScan(qrData) {
    this.trackEvent('qr_code_scan', {
      qr_data: qrData,
      scan_location: 'web_scanner'
    });
  }

  trackEmailSent(emailData) {
    this.trackEvent('email_sent', {
      recipient: emailData.recipient,
      email_type: emailData.type,
      success: emailData.success
    });
  }

  trackPDFGenerated(pdfData) {
    this.trackEvent('pdf_generated', {
      event_id: pdfData.event_id,
      user_email: pdfData.user_email,
      generation_time: pdfData.generation_time
    });
  }

  // Invia i dati al server
  async flush(isSync = false) {
    if (this.events.length === 0 && this.pageViews.length === 0) {
      return;
    }

    const data = {
      session_id: this.sessionId,
      user_id: this.userId,
      events: [...this.events],
      page_views: [...this.pageViews],
      timestamp: new Date().toISOString()
    };

    try {
      const response = await fetch('/api/analytics', {
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
      console.error('Errore invio analytics:', error);
    }
  }

  // Utility per ottenere metriche di performance
  getFirstPaint() {
    const paintEntries = performance.getEntriesByType('paint');
    const firstPaint = paintEntries.find(entry => entry.name === 'first-paint');
    return firstPaint ? firstPaint.startTime : null;
  }

  getFirstContentfulPaint() {
    const paintEntries = performance.getEntriesByType('paint');
    const firstContentfulPaint = paintEntries.find(entry => entry.name === 'first-contentful-paint');
    return firstContentfulPaint ? firstContentfulPaint.startTime : null;
  }

  getLargestContentfulPaint() {
    const lcpEntries = performance.getEntriesByType('largest-contentful-paint');
    return lcpEntries.length > 0 ? lcpEntries[lcpEntries.length - 1].startTime : null;
  }

  // Metodi per abilitare/disabilitare il tracking
  enable() {
    this.isEnabled = true;
    this.init();
  }

  disable() {
    this.isEnabled = false;
  }

  // Ottiene statistiche della sessione corrente
  getSessionStats() {
    return {
      session_id: this.sessionId,
      user_id: this.userId,
      session_duration: Date.now() - this.startTime,
      events_count: this.events.length,
      page_views_count: this.pageViews.length,
      is_enabled: this.isEnabled
    };
  }
}

// Crea un'istanza singleton
const analytics = new AnalyticsService();

export default analytics;
