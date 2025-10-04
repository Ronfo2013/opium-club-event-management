import React, { useState } from 'react';
import { useEvents, useDeleteEvent, useCloseEvent, useReopenEvent, useCreateEvent } from '../../hooks/useEvents';
import { toast } from 'react-hot-toast';
import LoadingSpinner from '../common/LoadingSpinner';

const EventManagement = () => {
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [newEvent, setNewEvent] = useState({
    titolo: '',
    event_date: '',
    background_image: null,
    chiuso: 0
  });

  const { data: events, isLoading, error } = useEvents();
  const deleteEvent = useDeleteEvent();
  const closeEvent = useCloseEvent();
  const reopenEvent = useReopenEvent();
  const createEvent = useCreateEvent();

  const handleDeleteEvent = async (eventId) => {
    if (window.confirm('Sei sicuro di voler eliminare questo evento?')) {
      try {
        await deleteEvent.mutateAsync(eventId);
        toast.success('Evento eliminato con successo');
      } catch (error) {
        toast.error('Errore durante l\'eliminazione dell\'evento');
      }
    }
  };

  const handleCloseEvent = async (eventId) => {
    try {
      await closeEvent.mutateAsync(eventId);
      toast.success('Evento chiuso con successo');
    } catch (error) {
      toast.error('Errore durante la chiusura dell\'evento');
    }
  };

  const handleReopenEvent = async (eventId) => {
    try {
      await reopenEvent.mutateAsync(eventId);
      toast.success('Evento riaperto con successo');
    } catch (error) {
      toast.error('Errore durante la riapertura dell\'evento');
    }
  };

  const handleCreateEvent = async (e) => {
    e.preventDefault();
    
    // Validazione
    if (!newEvent.titolo.trim()) {
      toast.error('Il titolo è obbligatorio');
      return;
    }
    
    if (!newEvent.event_date) {
      toast.error('La data è obbligatoria');
      return;
    }
    
    try {
      await createEvent.mutateAsync({
        titolo: newEvent.titolo.trim(),
        event_date: newEvent.event_date,
        background_image: newEvent.background_image,
        chiuso: newEvent.chiuso
      });
      
      toast.success('Evento creato con successo');
      setShowCreateForm(false);
      setNewEvent({ titolo: '', event_date: '', background_image: null, chiuso: 0 });
    } catch (error) {
      toast.error('Errore durante la creazione dell\'evento');
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-8">
        <LoadingSpinner size="lg" />
        <span className="ml-2">Caricamento eventi...</span>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-md p-4">
        <p className="text-red-800">Errore nel caricamento degli eventi: {error.message}</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header con pulsante crea */}
      <div className="flex justify-between items-center">
        <h2 className="text-2xl font-bold text-gray-900">Gestione Eventi</h2>
        <button
          onClick={() => setShowCreateForm(!showCreateForm)}
          className="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md font-medium"
        >
          {showCreateForm ? 'Annulla' : 'Crea Nuovo Evento'}
        </button>
      </div>

      {/* Form creazione evento */}
      {showCreateForm && (
        <div className="bg-white rounded-lg shadow-md p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Crea Nuovo Evento</h3>
          <form onSubmit={handleCreateEvent} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label htmlFor="titolo" className="block text-sm font-medium text-gray-700 mb-2">
                  Titolo Evento
                </label>
                <input
                  type="text"
                  id="titolo"
                  value={newEvent.titolo}
                  onChange={(e) => setNewEvent({ ...newEvent, titolo: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                  required
                />
              </div>
              <div>
                <label htmlFor="event_date" className="block text-sm font-medium text-gray-700 mb-2">
                  Data Evento
                </label>
                <input
                  type="date"
                  id="event_date"
                  value={newEvent.event_date}
                  onChange={(e) => setNewEvent({ ...newEvent, event_date: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                  required
                />
              </div>
            </div>
            <div className="flex justify-end space-x-3">
              <button
                type="button"
                onClick={() => setShowCreateForm(false)}
                className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
              >
                Annulla
              </button>
              <button
                type="submit"
                className="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700"
              >
                Crea Evento
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Statistiche */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white rounded-lg shadow-md p-6">
          <div className="flex items-center">
            <div className="p-2 bg-blue-100 rounded-lg">
              <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Totale Eventi</p>
              <p className="text-2xl font-semibold text-gray-900">{events?.length || 0}</p>
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
              <p className="text-sm font-medium text-gray-600">Eventi Aperti</p>
              <p className="text-2xl font-semibold text-gray-900">
                {events?.filter(e => e.chiuso == 0).length || 0}
              </p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow-md p-6">
          <div className="flex items-center">
            <div className="p-2 bg-red-100 rounded-lg">
              <svg className="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Eventi Chiusi</p>
              <p className="text-2xl font-semibold text-gray-900">
                {events?.filter(e => e.chiuso == 1).length || 0}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Lista eventi */}
      <div className="bg-white rounded-lg shadow-md overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-200">
          <h3 className="text-lg font-medium text-gray-900">Lista Eventi</h3>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Titolo
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Data
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
              {events?.map((event) => (
                <tr key={event.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm font-medium text-gray-900">{event.titolo}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">
                      {new Date(event.event_date).toLocaleDateString('it-IT')}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                      event.chiuso == 0 
                        ? 'bg-green-100 text-green-800' 
                        : 'bg-red-100 text-red-800'
                    }`}>
                      {event.chiuso == 0 ? 'Aperto' : 'Chiuso'}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {new Date(event.created_at).toLocaleDateString('it-IT')}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                    {event.chiuso == 0 ? (
                      <button
                        onClick={() => handleCloseEvent(event.id)}
                        disabled={closeEvent.isLoading}
                        className="text-yellow-600 hover:text-yellow-900 disabled:opacity-50"
                      >
                        Chiudi
                      </button>
                    ) : (
                      <button
                        onClick={() => handleReopenEvent(event.id)}
                        disabled={reopenEvent.isLoading}
                        className="text-green-600 hover:text-green-900 disabled:opacity-50"
                      >
                        Riapri
                      </button>
                    )}
                    <button
                      onClick={() => handleDeleteEvent(event.id)}
                      disabled={deleteEvent.isLoading}
                      className="text-red-600 hover:text-red-900 disabled:opacity-50"
                    >
                      Elimina
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {(!events || events.length === 0) && (
          <div className="text-center py-8">
            <p className="text-gray-500">Nessun evento trovato</p>
          </div>
        )}
      </div>
    </div>
  );
};

export default EventManagement;