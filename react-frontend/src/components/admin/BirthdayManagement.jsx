import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { toast } from 'react-hot-toast';
import axios from 'axios';
import LoadingSpinner from '../common/LoadingSpinner';

const BirthdayManagement = () => {
  const [showTemplateForm, setShowTemplateForm] = useState(false);
  const [newTemplate, setNewTemplate] = useState({
    name: '',
    subject: '',
    html_content: '',
    background_image: null
  });
  const queryClient = useQueryClient();

  // Fetch birthday data
  const { data: birthdayData, isLoading, error } = useQuery('birthdays', async () => {
    const response = await axios.get('http://localhost:8000/api/birthdays.php');
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to fetch birthday data');
    }
    return response.data.data;
  });

  // Send birthday mutation
  const sendBirthdayMutation = useMutation(async ({ userId, templateId }) => {
    const response = await axios.post('http://localhost:8000/api/birthdays.php', {
      action: 'send_birthday',
      user_id: userId,
      template_id: templateId
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to send birthday');
    }
    return response.data;
  }, {
    onSuccess: (data) => {
      queryClient.invalidateQueries('birthdays');
      toast.success(data.message);
    },
    onError: (error) => {
      toast.error(error.message || 'Errore durante l\'invio degli auguri');
    }
  });

  // Create template mutation
  const createTemplateMutation = useMutation(async (templateData) => {
    const response = await axios.post('http://localhost:8000/api/birthdays.php', {
      action: 'create_template',
      ...templateData
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to create template');
    }
    return response.data;
  }, {
    onSuccess: () => {
      queryClient.invalidateQueries('birthdays');
      toast.success('Template creato con successo');
      setShowTemplateForm(false);
      setNewTemplate({ name: '', subject: '', html_content: '', background_image: null });
    },
    onError: (error) => {
      toast.error(error.message || 'Errore durante la creazione del template');
    }
  });

  // Activate template mutation
  const activateTemplateMutation = useMutation(async (templateId) => {
    const response = await axios.post('http://localhost:8000/api/birthdays.php', {
      action: 'activate_template',
      template_id: templateId
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to activate template');
    }
    return response.data;
  }, {
    onSuccess: () => {
      queryClient.invalidateQueries('birthdays');
      toast.success('Template attivato con successo');
    },
    onError: (error) => {
      toast.error(error.message || 'Errore durante l\'attivazione del template');
    }
  });

  const handleSendBirthday = (userId) => {
    if (!birthdayData?.active_template) {
      toast.error('Nessun template attivo. Attiva prima un template.');
      return;
    }
    sendBirthdayMutation.mutate({
      userId,
      templateId: birthdayData.active_template.id
    });
  };

  const handleCreateTemplate = (e) => {
    e.preventDefault();
    createTemplateMutation.mutate(newTemplate);
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-8">
        <LoadingSpinner size="lg" />
        <span className="ml-2">Caricamento dati compleanni...</span>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-md p-4">
        <p className="text-red-800">Errore nel caricamento: {error.message}</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <h2 className="text-2xl font-bold text-gray-900">Gestione Compleanni</h2>
        <button
          onClick={() => setShowTemplateForm(!showTemplateForm)}
          className="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md font-medium"
        >
          {showTemplateForm ? 'Annulla' : 'Crea Template'}
        </button>
      </div>

      {/* Statistiche */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white rounded-lg shadow-md p-6">
          <div className="flex items-center">
            <div className="p-2 bg-blue-100 rounded-lg">
              <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Compleanni Oggi</p>
              <p className="text-2xl font-semibold text-gray-900">
                {birthdayData?.stats?.total_birthdays_today || 0}
              </p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow-md p-6">
          <div className="flex items-center">
            <div className="p-2 bg-green-100 rounded-lg">
              <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Auguri Inviati</p>
              <p className="text-2xl font-semibold text-gray-900">
                {birthdayData?.stats?.sent_today || 0}
              </p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow-md p-6">
          <div className="flex items-center">
            <div className="p-2 bg-yellow-100 rounded-lg">
              <svg className="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">In Attesa</p>
              <p className="text-2xl font-semibold text-gray-900">
                {birthdayData?.stats?.pending_today || 0}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Template attivo */}
      {birthdayData?.active_template && (
        <div className="bg-white rounded-lg shadow-md p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Template Attivo</h3>
          <div className="bg-gray-50 p-4 rounded-md">
            <h4 className="font-medium text-gray-900">{birthdayData.active_template.name}</h4>
            <p className="text-sm text-gray-600 mt-1">Oggetto: {birthdayData.active_template.subject}</p>
          </div>
        </div>
      )}

      {/* Form creazione template */}
      {showTemplateForm && (
        <div className="bg-white rounded-lg shadow-md p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Crea Nuovo Template</h3>
          <form onSubmit={handleCreateTemplate} className="space-y-4">
            <div>
              <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-2">
                Nome Template
              </label>
              <input
                type="text"
                id="name"
                value={newTemplate.name}
                onChange={(e) => setNewTemplate({ ...newTemplate, name: e.target.value })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                required
              />
            </div>
            <div>
              <label htmlFor="subject" className="block text-sm font-medium text-gray-700 mb-2">
                Oggetto Email
              </label>
              <input
                type="text"
                id="subject"
                value={newTemplate.subject}
                onChange={(e) => setNewTemplate({ ...newTemplate, subject: e.target.value })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                required
              />
            </div>
            <div>
              <label htmlFor="html_content" className="block text-sm font-medium text-gray-700 mb-2">
                Contenuto HTML
              </label>
              <textarea
                id="html_content"
                rows={6}
                value={newTemplate.html_content}
                onChange={(e) => setNewTemplate({ ...newTemplate, html_content: e.target.value })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                placeholder="Inserisci il contenuto HTML del template..."
                required
              />
            </div>
            <div className="flex justify-end space-x-3">
              <button
                type="button"
                onClick={() => setShowTemplateForm(false)}
                className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
              >
                Annulla
              </button>
              <button
                type="submit"
                disabled={createTemplateMutation.isLoading}
                className="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 disabled:opacity-50"
              >
                {createTemplateMutation.isLoading ? 'Creazione...' : 'Crea Template'}
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Lista utenti con compleanno oggi */}
      <div className="bg-white rounded-lg shadow-md overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-200">
          <h3 className="text-lg font-medium text-gray-900">
            Compleanni Oggi ({birthdayData?.today || 'N/A'})
          </h3>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Nome
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Email
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Evento
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Data Nascita
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Stato Auguri
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Azioni
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {birthdayData?.birthday_users?.map((user) => (
                <tr key={user.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm font-medium text-gray-900">
                      {user.nome} {user.cognome}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{user.email}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{user.evento_titolo || 'N/A'}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">
                      {new Date(user.data_nascita).toLocaleDateString('it-IT')}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                      In Attesa
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button
                      onClick={() => handleSendBirthday(user.id)}
                      disabled={sendBirthdayMutation.isLoading || !birthdayData?.active_template}
                      className="text-purple-600 hover:text-purple-900 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {sendBirthdayMutation.isLoading ? 'Invio...' : 'Invia Auguri'}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {(!birthdayData?.birthday_users || birthdayData.birthday_users.length === 0) && (
          <div className="text-center py-8">
            <p className="text-gray-500">Nessun compleanno oggi</p>
          </div>
        )}
      </div>

      {/* Lista template */}
      <div className="bg-white rounded-lg shadow-md overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-200">
          <h3 className="text-lg font-medium text-gray-900">Template Disponibili</h3>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Nome
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Oggetto
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Stato
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Data Creazione
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Azioni
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {birthdayData?.templates?.map((template) => (
                <tr key={template.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm font-medium text-gray-900">{template.name}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{template.subject}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                      template.is_active 
                        ? 'bg-green-100 text-green-800' 
                        : 'bg-gray-100 text-gray-800'
                    }`}>
                      {template.is_active ? 'Attivo' : 'Inattivo'}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {new Date(template.created_at).toLocaleDateString('it-IT')}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    {!template.is_active && (
                      <button
                        onClick={() => activateTemplateMutation.mutate(template.id)}
                        disabled={activateTemplateMutation.isLoading}
                        className="text-green-600 hover:text-green-900 disabled:opacity-50"
                      >
                        Attiva
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {(!birthdayData?.templates || birthdayData.templates.length === 0) && (
          <div className="text-center py-8">
            <p className="text-gray-500">Nessun template disponibile</p>
          </div>
        )}
      </div>
    </div>
  );
};

export default BirthdayManagement;





