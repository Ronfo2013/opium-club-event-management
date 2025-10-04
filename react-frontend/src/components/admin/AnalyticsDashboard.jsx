import React, { useState, useEffect } from 'react';
import { 
  BarChart3, 
  Users, 
  Eye, 
  MousePointer, 
  Clock, 
  TrendingUp,
  Calendar,
  Globe,
  Smartphone,
  Monitor
} from 'lucide-react';
import { useAnalytics } from '../../hooks/useAnalytics';

const AnalyticsDashboard = () => {
  const [analyticsData, setAnalyticsData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [timeRange, setTimeRange] = useState('7d');
  const { getSessionStats } = useAnalytics();

  useEffect(() => {
    fetchAnalyticsData();
  }, [timeRange]);

  const fetchAnalyticsData = async () => {
    try {
      setLoading(true);
      const response = await fetch(`/api/analytics/dashboard?range=${timeRange}`);
      const data = await response.json();
      setAnalyticsData(data);
    } catch (error) {
      console.error('Errore caricamento analytics:', error);
    } finally {
      setLoading(false);
    }
  };

  const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
  };

  const formatDuration = (seconds) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    return `${hours}h ${minutes}m`;
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500"></div>
      </div>
    );
  }

  if (!analyticsData) {
    return (
      <div className="text-center py-8">
        <BarChart3 className="mx-auto h-12 w-12 text-gray-400" />
        <h3 className="mt-2 text-sm font-medium text-gray-900">Nessun dato disponibile</h3>
        <p className="mt-1 text-sm text-gray-500">I dati di analytics verranno mostrati qui.</p>
      </div>
    );
  }

  const stats = analyticsData.stats || {};
  const topPages = analyticsData.topPages || [];
  const topEvents = analyticsData.topEvents || [];
  const deviceStats = analyticsData.deviceStats || {};

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-bold text-gray-900">Analytics Dashboard</h2>
          <p className="text-gray-600">Statistiche di utilizzo e performance</p>
        </div>
        <select
          value={timeRange}
          onChange={(e) => setTimeRange(e.target.value)}
          className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
        >
          <option value="1d">Ultimo giorno</option>
          <option value="7d">Ultimi 7 giorni</option>
          <option value="30d">Ultimi 30 giorni</option>
          <option value="90d">Ultimi 90 giorni</option>
        </select>
      </div>

      {/* Statistiche principali */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="bg-white p-6 rounded-lg shadow-sm border">
          <div className="flex items-center">
            <div className="p-2 bg-blue-100 rounded-lg">
              <Users className="h-6 w-6 text-blue-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Utenti Unici</p>
              <p className="text-2xl font-bold text-gray-900">{formatNumber(stats.uniqueUsers || 0)}</p>
            </div>
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow-sm border">
          <div className="flex items-center">
            <div className="p-2 bg-green-100 rounded-lg">
              <Eye className="h-6 w-6 text-green-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Visualizzazioni</p>
              <p className="text-2xl font-bold text-gray-900">{formatNumber(stats.pageViews || 0)}</p>
            </div>
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow-sm border">
          <div className="flex items-center">
            <div className="p-2 bg-purple-100 rounded-lg">
              <MousePointer className="h-6 w-6 text-purple-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Eventi</p>
              <p className="text-2xl font-bold text-gray-900">{formatNumber(stats.totalEvents || 0)}</p>
            </div>
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow-sm border">
          <div className="flex items-center">
            <div className="p-2 bg-orange-100 rounded-lg">
              <Clock className="h-6 w-6 text-orange-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Durata Media</p>
              <p className="text-2xl font-bold text-gray-900">{formatDuration(stats.avgSessionDuration || 0)}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Grafici e tabelle */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Top Pages */}
        <div className="bg-white p-6 rounded-lg shadow-sm border">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Pagine Più Visitate</h3>
          <div className="space-y-3">
            {topPages.map((page, index) => (
              <div key={index} className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <span className="text-sm font-medium text-gray-500">#{index + 1}</span>
                  <span className="text-sm text-gray-900">{page.page}</span>
                </div>
                <span className="text-sm font-semibold text-gray-900">{formatNumber(page.views)}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Top Events */}
        <div className="bg-white p-6 rounded-lg shadow-sm border">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Eventi Più Frequenti</h3>
          <div className="space-y-3">
            {topEvents.map((event, index) => (
              <div key={index} className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <span className="text-sm font-medium text-gray-500">#{index + 1}</span>
                  <span className="text-sm text-gray-900">{event.event_name}</span>
                </div>
                <span className="text-sm font-semibold text-gray-900">{formatNumber(event.count)}</span>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Statistiche dispositivi */}
      <div className="bg-white p-6 rounded-lg shadow-sm border">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Dispositivi</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="text-center">
            <div className="flex justify-center mb-2">
              <Smartphone className="h-8 w-8 text-blue-600" />
            </div>
            <p className="text-sm text-gray-600">Mobile</p>
            <p className="text-2xl font-bold text-gray-900">{deviceStats.mobile || 0}%</p>
          </div>
          <div className="text-center">
            <div className="flex justify-center mb-2">
              <Monitor className="h-8 w-8 text-green-600" />
            </div>
            <p className="text-sm text-gray-600">Desktop</p>
            <p className="text-2xl font-bold text-gray-900">{deviceStats.desktop || 0}%</p>
          </div>
          <div className="text-center">
            <div className="flex justify-center mb-2">
              <Globe className="h-8 w-8 text-purple-600" />
            </div>
            <p className="text-sm text-gray-600">Tablet</p>
            <p className="text-2xl font-bold text-gray-900">{deviceStats.tablet || 0}%</p>
          </div>
        </div>
      </div>

      {/* Statistiche sessione corrente */}
      <div className="bg-white p-6 rounded-lg shadow-sm border">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Sessione Corrente</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <p className="text-sm text-gray-600">ID Sessione</p>
            <p className="text-sm font-mono text-gray-900">{getSessionStats().session_id}</p>
          </div>
          <div>
            <p className="text-sm text-gray-600">Durata</p>
            <p className="text-sm text-gray-900">{formatDuration(getSessionStats().session_duration / 1000)}</p>
          </div>
          <div>
            <p className="text-sm text-gray-600">Eventi</p>
            <p className="text-sm text-gray-900">{getSessionStats().events_count}</p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default AnalyticsDashboard;
