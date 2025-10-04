import React, { useState } from 'react';
import { useUsers, useValidateUser, useRejectUser } from '../../hooks/useUsers';
import LoadingSpinner from '../common/LoadingSpinner';

const UserManagement = () => {
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  
  const { data: users, isLoading, refetch } = useUsers();
  const validateUser = useValidateUser();
  const rejectUser = useRejectUser();

  const handleValidate = async (userId) => {
    try {
      await validateUser.mutateAsync(userId);
      refetch();
    } catch (error) {
      console.error('Error validating user:', error);
    }
  };

  const handleReject = async (userId) => {
    if (window.confirm('Sei sicuro di voler rifiutare questo utente?')) {
      try {
        await rejectUser.mutateAsync(userId);
        refetch();
      } catch (error) {
        console.error('Error rejecting user:', error);
      }
    }
  };

  // Filter users based on search term and status
  const filteredUsers = users?.filter(user => {
    const matchesSearch = 
      user.first_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      user.last_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      user.email?.toLowerCase().includes(searchTerm.toLowerCase());
    
    const matchesStatus = 
      statusFilter === 'all' ||
      (statusFilter === 'pending' && !user.is_validated) ||
      (statusFilter === 'validated' && user.is_validated) ||
      (statusFilter === 'rejected' && user.is_rejected);
    
    return matchesSearch && matchesStatus;
  });

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h2 className="text-2xl font-bold text-gray-900">Gestione Utenti</h2>
      </div>

      {/* Filters */}
      <div className="card">
        <div className="card-body">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="form-label">Cerca Utenti</label>
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="input"
                placeholder="Nome, cognome o email..."
              />
            </div>
            <div>
              <label className="form-label">Filtra per Stato</label>
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="input"
              >
                <option value="all">Tutti</option>
                <option value="pending">In Attesa</option>
                <option value="validated">Validati</option>
                <option value="rejected">Rifiutati</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      {/* Users List */}
      <div className="card">
        <div className="card-header">
          <h3 className="text-lg font-semibold">Lista Utenti</h3>
        </div>
        <div className="card-body">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Utente
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Email
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Evento
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Data Registrazione
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Stato Email
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Stato Validazione
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Azioni
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {filteredUsers?.map((user) => (
                  <tr key={user.id}>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm font-medium text-gray-900">
                        {user.first_name} {user.last_name}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-gray-900">{user.email}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-gray-900">{user.event?.title || 'N/A'}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-gray-900">
                        {new Date(user.created_at).toLocaleDateString('it-IT')}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                        user.email_status === 'sent' ? 'bg-green-100 text-green-800' :
                        user.email_status === 'failed' ? 'bg-red-100 text-red-800' :
                        'bg-yellow-100 text-yellow-800'
                      }`}>
                        {user.email_status || 'pending'}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                        user.is_validated ? 'bg-green-100 text-green-800' :
                        user.is_rejected ? 'bg-red-100 text-red-800' :
                        'bg-yellow-100 text-yellow-800'
                      }`}>
                        {user.is_validated ? 'Validato' :
                         user.is_rejected ? 'Rifiutato' : 'In Attesa'}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                      {!user.is_validated && !user.is_rejected && (
                        <>
                          <button
                            onClick={() => handleValidate(user.id)}
                            disabled={validateUser.isLoading}
                            className="text-green-600 hover:text-green-900 disabled:opacity-50"
                          >
                            {validateUser.isLoading ? 'Validazione...' : 'Valida'}
                          </button>
                          <button
                            onClick={() => handleReject(user.id)}
                            disabled={rejectUser.isLoading}
                            className="text-red-600 hover:text-red-900 disabled:opacity-50"
                          >
                            {rejectUser.isLoading ? 'Rifiuto...' : 'Rifiuta'}
                          </button>
                        </>
                      )}
                    </td>
                  </tr>
                ))}
                {(!filteredUsers || filteredUsers.length === 0) && (
                  <tr>
                    <td colSpan="7" className="px-6 py-4 text-center text-gray-500">
                      Nessun utente trovato
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
};

export default UserManagement;