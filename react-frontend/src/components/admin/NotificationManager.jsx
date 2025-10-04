import React, { useState, useEffect } from 'react';
import { Bell, Send, Users, CheckCircle, XCircle } from 'lucide-react';
import notificationService from '../../services/notifications';
import { useAnalytics } from '../../hooks/useAnalytics';

const NotificationManager = () => {
  const [isSubscribed, setIsSubscribed] = useState(false);
  const [loading, setLoading] = useState(false);
  const [notificationForm, setNotificationForm] = useState({
    title: '',
    body: '',
    url: '/',
    data: {}
  });
  const [subscribersCount, setSubscribersCount] = useState(0);
  const { trackAdminAction } = useAnalytics();

  useEffect(() => {
    checkSubscriptionStatus();
    fetchSubscribersCount();
  }, []);

  const checkSubscriptionStatus = async () => {
    try {
      const subscribed = await notificationService.isSubscribed();
      setIsSubscribed(subscribed);
    } catch (error) {
      console.error('Errore controllo sottoscrizione:', error);
    }
  };

  const fetchSubscribersCount = async () => {
    try {
      const response = await fetch('/api/notifications/subscribers-count');
      const data = await response.json();
      setSubscribersCount(data.count || 0);
    } catch (error) {
      console.error('Errore conteggio sottoscrittori:', error);
    }
  };

  const handleSubscribe = async () => {
    setLoading(true);
    try {
      const hasPermission = await notificationService.requestPermission();
      if (hasPermission) {
        await notificationService.subscribe();
        setIsSubscribed(true);
        trackAdminAction('notification_subscribe');
      }
    } catch (error) {
      console.error('Errore sottoscrizione:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleUnsubscribe = async () => {
    setLoading(true);
    try {
      await notificationService.unsubscribe();
      setIsSubscribed(false);
      trackAdminAction('notification_unsubscribe');
    } catch (error) {
      console.error('Errore annullamento sottoscrizione:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleSendNotification = async (e) => {
    e.preventDefault();
    if (!notificationForm.title.trim()) return;

    setLoading(true);
    try {
      const response = await fetch('/api/notifications/send', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(notificationForm)
      });

      const result = await response.json();
      
      if (result.success) {
        trackAdminAction('notification_sent', {
          title: notificationForm.title,
          recipients: result.sent
        });
        
        setNotificationForm({
          title: '',
          body: '',
          url: '/',
          data: {}
        });
      }
    } catch (error) {
      console.error('Errore invio notifica:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-bold text-gray-900">Gestione Notifiche</h2>
          <p className="text-gray-600">Invia notifiche push agli utenti</p>
        </div>
        <div className="flex items-center space-x-4">
          <div className="text-center">
            <Users className="h-8 w-8 text-blue-600 mx-auto" />
            <p className="text-sm text-gray-600">Sottoscrittori</p>
            <p className="text-lg font-bold text-gray-900">{subscribersCount}</p>
          </div>
        </div>
      </div>

      {/* Stato sottoscrizione */}
      <div className="bg-white p-6 rounded-lg shadow-sm border">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Stato Notifiche</h3>
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <Bell className="h-6 w-6 text-gray-600" />
            <div>
              <p className="text-sm font-medium text-gray-900">
                {isSubscribed ? 'Notifiche abilitate' : 'Notifiche disabilitate'}
              </p>
              <p className="text-sm text-gray-600">
                {isSubscribed 
                  ? 'Riceverai notifiche push per eventi importanti'
                  : 'Abilita le notifiche per ricevere aggiornamenti'
                }
              </p>
            </div>
          </div>
          <button
            onClick={isSubscribed ? handleUnsubscribe : handleSubscribe}
            disabled={loading}
            className={`px-4 py-2 rounded-md font-medium ${
              isSubscribed
                ? 'bg-red-100 text-red-700 hover:bg-red-200'
                : 'bg-blue-100 text-blue-700 hover:bg-blue-200'
            } disabled:opacity-50`}
          >
            {loading ? 'Caricamento...' : (isSubscribed ? 'Disabilita' : 'Abilita')}
          </button>
        </div>
      </div>

      {/* Form invio notifica */}
      <div className="bg-white p-6 rounded-lg shadow-sm border">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Invia Notifica</h3>
        <form onSubmit={handleSendNotification} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Titolo *
            </label>
            <input
              type="text"
              value={notificationForm.title}
              onChange={(e) => setNotificationForm({...notificationForm, title: e.target.value})}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
              placeholder="Titolo della notifica"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Messaggio
            </label>
            <textarea
              value={notificationForm.body}
              onChange={(e) => setNotificationForm({...notificationForm, body: e.target.value})}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
              rows={3}
              placeholder="Messaggio della notifica"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              URL di destinazione
            </label>
            <input
              type="url"
              value={notificationForm.url}
              onChange={(e) => setNotificationForm({...notificationForm, url: e.target.value})}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
              placeholder="/admin"
            />
          </div>

          <button
            type="submit"
            disabled={loading || !notificationForm.title.trim()}
            className="w-full bg-gradient-to-r from-purple-500 to-pink-500 text-white py-2 px-4 rounded-md hover:from-purple-600 hover:to-pink-600 disabled:opacity-50 flex items-center justify-center space-x-2"
          >
            <Send className="h-4 w-4" />
            <span>{loading ? 'Invio in corso...' : 'Invia Notifica'}</span>
          </button>
        </form>
      </div>

      {/* Template notifiche */}
      <div className="bg-white p-6 rounded-lg shadow-sm border">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Template Rapidi</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <button
            onClick={() => setNotificationForm({
              title: 'Nuovo Evento Disponibile!',
              body: 'Un nuovo evento è stato aggiunto. Registrati ora!',
              url: '/',
              data: {}
            })}
            className="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 text-left"
          >
            <h4 className="font-medium text-gray-900">Nuovo Evento</h4>
            <p className="text-sm text-gray-600">Notifica per nuovi eventi</p>
          </button>

          <button
            onClick={() => setNotificationForm({
              title: 'Promemoria Evento',
              body: 'Il tuo evento inizia tra poco. Preparati!',
              url: '/admin',
              data: {}
            })}
            className="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 text-left"
          >
            <h4 className="font-medium text-gray-900">Promemoria</h4>
            <p className="text-sm text-gray-600">Promemoria per eventi</p>
          </button>
        </div>
      </div>
    </div>
  );
};

export default NotificationManager;
