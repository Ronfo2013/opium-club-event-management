import React from 'react';
import { useQuery } from 'react-query';
import { api } from '../../services/api';
import LoadingSpinner from '../common/LoadingSpinner';

const AdminDashboard = () => {
  const { data: stats, isLoading, error } = useQuery(
    'admin-stats',
    () => api.admin.stats(),
    {
      select: (response) => response.data.data,
      refetchInterval: 30000, // Refresh every 30 seconds
    }
  );

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="lg" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="text-center py-8">
        <p className="text-red-600">Errore nel caricamento delle statistiche</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h2 className="text-2xl font-bold text-gray-900">Dashboard</h2>
      
      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {/* Events Stats */}
        <div className="card">
          <div className="card-body">
            <div className="flex items-center">
              <div className="p-2 bg-blue-100 rounded-lg">
                <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Eventi Totali</p>
                <p className="text-2xl font-semibold text-gray-900">{stats?.events?.total || 0}</p>
              </div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-body">
            <div className="flex items-center">
              <div className="p-2 bg-green-100 rounded-lg">
                <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Eventi Aperti</p>
                <p className="text-2xl font-semibold text-gray-900">{stats?.events?.open || 0}</p>
              </div>
            </div>
          </div>
        </div>

        {/* Users Stats */}
        <div className="card">
          <div className="card-body">
            <div className="flex items-center">
              <div className="p-2 bg-purple-100 rounded-lg">
                <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                </svg>
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Utenti Totali</p>
                <p className="text-2xl font-semibold text-gray-900">{stats?.users?.total || 0}</p>
              </div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-body">
            <div className="flex items-center">
              <div className="p-2 bg-yellow-100 rounded-lg">
                <svg className="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">In Attesa</p>
                <p className="text-2xl font-semibold text-gray-900">{stats?.users?.pending_validation || 0}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Recent Activity */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Registrations */}
        <div className="card">
          <div className="card-header">
            <h3 className="text-lg font-semibold text-gray-900">Registrazioni Recenti</h3>
          </div>
          <div className="card-body">
            <div className="space-y-4">
              {stats?.recent_activity?.recent_registrations?.slice(0, 5).map((user) => (
                <div key={user.id} className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-gray-900">
                      {user.first_name} {user.last_name}
                    </p>
                    <p className="text-sm text-gray-600">{user.event?.title}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-sm text-gray-500">
                      {new Date(user.created_at).toLocaleDateString('it-IT')}
                    </p>
                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                      user.email_status === 'sent' ? 'bg-green-100 text-green-800' :
                      user.email_status === 'failed' ? 'bg-red-100 text-red-800' :
                      'bg-yellow-100 text-yellow-800'
                    }`}>
                      {user.email_status}
                    </span>
                  </div>
                </div>
              )) || (
                <p className="text-gray-500 text-center py-4">Nessuna registrazione recente</p>
              )}
            </div>
          </div>
        </div>

        {/* Recent Validations */}
        <div className="card">
          <div className="card-header">
            <h3 className="text-lg font-semibold text-gray-900">Validazioni Recenti</h3>
          </div>
          <div className="card-body">
            <div className="space-y-4">
              {stats?.recent_activity?.recent_validations?.slice(0, 5).map((user) => (
                <div key={user.id} className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-gray-900">
                      {user.first_name} {user.last_name}
                    </p>
                    <p className="text-sm text-gray-600">{user.event?.title}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-sm text-gray-500">
                      {new Date(user.validated_at).toLocaleDateString('it-IT')}
                    </p>
                    <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                      Validato
                    </span>
                  </div>
                </div>
              )) || (
                <p className="text-gray-500 text-center py-4">Nessuna validazione recente</p>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default AdminDashboard;






