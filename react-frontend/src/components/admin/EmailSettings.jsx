import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { toast } from 'react-hot-toast';
import axios from 'axios';
import LoadingSpinner from '../common/LoadingSpinner';

const EmailSettings = () => {
  const [testEmail, setTestEmail] = useState('');
  const queryClient = useQueryClient();

  // Fetch email settings
  const { data: emailData, isLoading, error } = useQuery('emailSettings', async () => {
    const response = await axios.get('http://localhost:8000/api/email-settings.php');
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to fetch email settings');
    }
    return response.data.data;
  });

  // Update email settings mutation
  const updateEmailMutation = useMutation(async (settings) => {
    const response = await axios.post('http://localhost:8000/api/email-settings.php', {
      action: 'update',
      ...settings
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to update email settings');
    }
    return response.data;
  }, {
    onSuccess: () => {
      queryClient.invalidateQueries('emailSettings');
      toast.success('Impostazioni email aggiornate con successo');
    },
    onError: (error) => {
      toast.error(error.message || 'Errore durante l\'aggiornamento');
    }
  });

  // Test email mutation
  const testEmailMutation = useMutation(async (email) => {
    const response = await axios.post('http://localhost:8000/api/email-settings.php', {
      action: 'test',
      test_email: email
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to send test email');
    }
    return response.data;
  }, {
    onSuccess: (data) => {
      toast.success(data.message);
    },
    onError: (error) => {
      toast.error(error.message || 'Errore durante l\'invio dell\'email di test');
    }
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const settings = {
      subject: formData.get('subject'),
      body: formData.get('body'),
      footer: formData.get('footer')
    };
    updateEmailMutation.mutate(settings);
  };

  const handleTestEmail = () => {
    if (!testEmail || !testEmail.includes('@')) {
      toast.error('Inserisci un\'email valida per il test');
      return;
    }
    testEmailMutation.mutate(testEmail);
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-8">
        <LoadingSpinner size="lg" />
        <span className="ml-2">Caricamento impostazioni email...</span>
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
      <div className="flex justify-between items-center">
        <h2 className="text-2xl font-bold text-gray-900">Impostazioni Email</h2>
      </div>

      {/* Form impostazioni email */}
      <div className="bg-white rounded-lg shadow-md p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Template Email di Conferma</h3>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label htmlFor="subject" className="block text-sm font-medium text-gray-700 mb-2">
              Oggetto Email
            </label>
            <input
              type="text"
              id="subject"
              name="subject"
              defaultValue={emailData?.subject || ''}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
              required
            />
          </div>
          
          <div>
            <label htmlFor="body" className="block text-sm font-medium text-gray-700 mb-2">
              Corpo Email
            </label>
            <textarea
              id="body"
              name="body"
              rows={8}
              defaultValue={emailData?.body || ''}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
              placeholder="Inserisci il contenuto dell'email di conferma..."
              required
            />
            <p className="text-sm text-gray-500 mt-1">
              Puoi usare le variabili: {`{nome}`, `{cognome}`, `{email}`, `{evento}`}
            </p>
          </div>
          
          <div>
            <label htmlFor="footer" className="block text-sm font-medium text-gray-700 mb-2">
              Footer Email
            </label>
            <input
              type="text"
              id="footer"
              name="footer"
              defaultValue={emailData?.footer || ''}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
            />
          </div>
          
          <div className="flex justify-end space-x-3">
            <button
              type="submit"
              disabled={updateEmailMutation.isLoading}
              className="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 disabled:opacity-50"
            >
              {updateEmailMutation.isLoading ? 'Salvataggio...' : 'Salva Impostazioni'}
            </button>
          </div>
        </form>
      </div>

      {/* Test email */}
      <div className="bg-white rounded-lg shadow-md p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Test Email</h3>
        <div className="flex space-x-3">
          <input
            type="email"
            value={testEmail}
            onChange={(e) => setTestEmail(e.target.value)}
            placeholder="Inserisci email per il test"
            className="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
          />
          <button
            onClick={handleTestEmail}
            disabled={testEmailMutation.isLoading}
            className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
          >
            {testEmailMutation.isLoading ? 'Invio...' : 'Invia Test'}
          </button>
        </div>
      </div>

      {/* Anteprima email */}
      <div className="bg-white rounded-lg shadow-md p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Anteprima Email</h3>
        <div className="border border-gray-200 rounded-md p-4 bg-gray-50">
          <div className="mb-2">
            <strong>Oggetto:</strong> {emailData?.subject || 'Nessun oggetto impostato'}
          </div>
          <div className="mb-4">
            <strong>Corpo:</strong>
            <div className="mt-2 p-3 bg-white rounded border">
              {emailData?.body ? (
                <div dangerouslySetInnerHTML={{ __html: emailData.body.replace(/\n/g, '<br>') }} />
              ) : (
                'Nessun contenuto impostato'
              )}
            </div>
          </div>
          <div>
            <strong>Footer:</strong> {emailData?.footer || 'Nessun footer impostato'}
          </div>
        </div>
      </div>
    </div>
  );
};

export default EmailSettings;





