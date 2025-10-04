import { useEffect, useCallback } from 'react';
import analytics from '../services/analytics';

export const useAnalytics = (componentName = 'UnknownComponent') => {
  // Traccia il caricamento del componente
  useEffect(() => {
    analytics.trackEvent('component_mount', {
      component: componentName,
      page: window.location.pathname
    });

    return () => {
      analytics.trackEvent('component_unmount', {
        component: componentName,
        page: window.location.pathname
      });
    };
  }, [componentName]);

  // Funzioni di tracking personalizzate
  const trackEvent = useCallback((eventName, properties = {}) => {
    analytics.trackEvent(eventName, properties);
  }, []);

  const trackPageView = useCallback((path) => {
    analytics.trackPageView(path);
  }, []);

  const trackUserRegistration = useCallback((userData) => {
    analytics.trackUserRegistration(userData);
  }, []);

  const trackEventRegistration = useCallback((eventData) => {
    analytics.trackEventRegistration(eventData);
  }, []);

  const trackAdminAction = useCallback((action, details = {}) => {
    analytics.trackAdminAction(action, details);
  }, []);

  const trackQRCodeScan = useCallback((qrData) => {
    analytics.trackQRCodeScan(qrData);
  }, []);

  const trackEmailSent = useCallback((emailData) => {
    analytics.trackEmailSent(emailData);
  }, []);

  const trackPDFGenerated = useCallback((pdfData) => {
    analytics.trackPDFGenerated(pdfData);
  }, []);

  const trackFormInteraction = useCallback((formName, action, fieldName = null) => {
    analytics.trackEvent('form_interaction', {
      form_name: formName,
      action: action,
      field_name: fieldName,
      page: window.location.pathname
    });
  }, []);

  const trackButtonClick = useCallback((buttonName, location = null) => {
    analytics.trackEvent('button_click', {
      button_name: buttonName,
      location: location || window.location.pathname,
      page: window.location.pathname
    });
  }, []);

  const trackError = useCallback((error, context = {}) => {
    analytics.trackEvent('error_occurred', {
      error_message: error.message || error,
      error_stack: error.stack || '',
      context: context,
      page: window.location.pathname
    });
  }, []);

  const trackPerformance = useCallback((metric, value, unit = 'ms') => {
    analytics.trackEvent('performance_metric', {
      metric: metric,
      value: value,
      unit: unit,
      page: window.location.pathname
    });
  }, []);

  const getSessionStats = useCallback(() => {
    return analytics.getSessionStats();
  }, []);

  return {
    trackEvent,
    trackPageView,
    trackUserRegistration,
    trackEventRegistration,
    trackAdminAction,
    trackQRCodeScan,
    trackEmailSent,
    trackPDFGenerated,
    trackFormInteraction,
    trackButtonClick,
    trackError,
    trackPerformance,
    getSessionStats
  };
};

// Hook specifico per il tracking delle pagine
export const usePageTracking = (pageName) => {
  const { trackPageView } = useAnalytics();

  useEffect(() => {
    trackPageView(pageName);
  }, [pageName, trackPageView]);
};

// Hook per il tracking degli errori
export const useErrorTracking = () => {
  const { trackError } = useAnalytics();

  useEffect(() => {
    const handleError = (event) => {
      trackError(event.error, {
        type: 'javascript_error',
        filename: event.filename,
        lineno: event.lineno,
        colno: event.colno
      });
    };

    const handleUnhandledRejection = (event) => {
      trackError(event.reason, {
        type: 'unhandled_promise_rejection'
      });
    };

    window.addEventListener('error', handleError);
    window.addEventListener('unhandledrejection', handleUnhandledRejection);

    return () => {
      window.removeEventListener('error', handleError);
      window.removeEventListener('unhandledrejection', handleUnhandledRejection);
    };
  }, [trackError]);
};

export default useAnalytics;
