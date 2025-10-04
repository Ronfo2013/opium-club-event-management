import { useEffect, useCallback } from 'react';
import mobileAnalytics from '../services/analytics';

export const useAnalytics = () => {
  // Inizializza l'analytics quando il componente viene montato
  useEffect(() => {
    mobileAnalytics.init();
  }, []);

  // Funzioni di tracking
  const trackEvent = useCallback((eventName, properties = {}) => {
    mobileAnalytics.trackEvent(eventName, properties);
  }, []);

  const trackScreenView = useCallback((screenName, properties = {}) => {
    mobileAnalytics.trackScreenView(screenName, properties);
  }, []);

  const trackUserRegistration = useCallback((userData) => {
    mobileAnalytics.trackUserRegistration(userData);
  }, []);

  const trackEventRegistration = useCallback((eventData) => {
    mobileAnalytics.trackEventRegistration(eventData);
  }, []);

  const trackQRCodeScan = useCallback((qrData) => {
    mobileAnalytics.trackQRCodeScan(qrData);
  }, []);

  const trackAdminAction = useCallback((action, details = {}) => {
    mobileAnalytics.trackAdminAction(action, details);
  }, []);

  const trackNotificationReceived = useCallback((notification) => {
    mobileAnalytics.trackNotificationReceived(notification);
  }, []);

  const trackNotificationOpened = useCallback((notification) => {
    mobileAnalytics.trackNotificationOpened(notification);
  }, []);

  const trackAppBackground = useCallback(() => {
    mobileAnalytics.trackAppBackground();
  }, []);

  const trackAppForeground = useCallback(() => {
    mobileAnalytics.trackAppForeground();
  }, []);

  const getSessionStats = useCallback(() => {
    return mobileAnalytics.getSessionStats();
  }, []);

  const flush = useCallback(() => {
    mobileAnalytics.flush();
  }, []);

  return {
    trackEvent,
    trackScreenView,
    trackUserRegistration,
    trackEventRegistration,
    trackQRCodeScan,
    trackAdminAction,
    trackNotificationReceived,
    trackNotificationOpened,
    trackAppBackground,
    trackAppForeground,
    getSessionStats,
    flush
  };
};

// Hook per il tracking delle schermate
export const useScreenTracking = (screenName) => {
  const { trackScreenView } = useAnalytics();

  useEffect(() => {
    trackScreenView(screenName);
  }, [screenName, trackScreenView]);
};

export default useAnalytics;
