import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { toast } from 'react-hot-toast';
import axios from 'axios';
import LoadingSpinner from '../common/LoadingSpinner';
import { getImageUrl } from '../../utils/imageUtils';

const EventCreationWithPDF = () => {
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [newEvent, setNewEvent] = useState({
    titolo: '',
    event_date: '',
    background_image: null
  });
  const [uploading, setUploading] = useState(false);
  const [previewImage, setPreviewImage] = useState(null);
  const queryClient = useQueryClient();

  // Fetch events
  const { data: events, isLoading, error } = useQuery('events', async () => {
    const response = await axios.get('http://localhost:8000/api/events.php');
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to fetch events');
    }
    return response.data.data;
  });

  // Create event mutation
  const createEventMutation = useMutation(async (eventData) => {
    const response = await axios.post('http://localhost:8000/api/event-actions.php', {
      action: 'create',
      ...eventData
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to create event');
    }
    return response.data;
  }, {
    onSuccess: () => {
      queryClient.invalidateQueries('events');
      toast.success('Evento creato con successo');
      setShowCreateForm(false);
      setNewEvent({ titolo: '', event_date: '', background_image: null });
      setPreviewImage(null);
    },
    onError: (error) => {
      toast.error(error.message || 'Errore durante la creazione dell\'evento');
    }
  });

  const handleImageUpload = async (file) => {
    setUploading(true);
    try {
      const formData = new FormData();
      formData.append('image', file);
      formData.append('type', 'pdf_background');

      const response = await axios.post('http://localhost:8000/api/image-upload.php', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      if (response.data.success) {
        setNewEvent(prev => ({
          ...prev,
          background_image: response.data.data.path
        }));
        
        // Mostra messaggio di successo o avviso
        if (response.data.warning) {
          toast.success('Immagine caricata con successo', {
            duration: 5000,
            style: {
              background: '#f59e0b',
              color: 'white',
            },
          });
          toast(response.data.warning, {
            duration: 8000,
            style: {
              background: '#f59e0b',
              color: 'white',
            },
          });
        } else {
          toast.success('Immagine caricata con successo');
        }
      } else {
        toast.error(response.data.message || 'Errore durante il caricamento');
      }
    } catch (error) {
      console.error('Upload error:', error);
      toast.error('Errore durante il caricamento dell\'immagine');
    } finally {
      setUploading(false);
    }
  };

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      // Validazione file
      if (!file.type.startsWith('image/')) {
        toast.error('Seleziona un file immagine valido');
        return;
      }
      
      if (file.size > 10 * 1024 * 1024) { // 10MB
        toast.error('Il file è troppo grande. Massimo 10MB');
        return;
      }

      // Anteprima
      const reader = new FileReader();
      reader.onload = (e) => {
        setPreviewImage(e.target.result);
      };
      reader.readAsDataURL(file);

      handleImageUpload(file);
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
      await createEventMutation.mutateAsync({
        titolo: newEvent.titolo.trim(),
        event_date: newEvent.event_date,
        background_image: newEvent.background_image
      });
    } catch (error) {
      // Error handling già gestito nel mutation
    }
  };

  const generateTestPDF = async (eventId) => {
    try {
      const response = await axios.post('http://localhost:8000/api/generate-test-pdf.php', {
        event_id: eventId
      });
      
      if (response.data.success) {
        toast.success('PDF di test generato con successo');
        // Apri il PDF in una nuova finestra
        window.open(response.data.pdf_url, '_blank');
      } else {
        toast.error(response.data.message || 'Errore durante la generazione del PDF');
      }
    } catch (error) {
      console.error('PDF generation error:', error);
      toast.error('Errore durante la generazione del PDF di test');
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
        <p className="text-red-800">Errore nel caricamento: {error.message}</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-bold text-gray-900">Gestione Eventi con PDF</h2>
          <p className="text-gray-600 mt-1">Crea eventi e carica immagini per la generazione automatica dei PDF</p>
        </div>
        <button
          onClick={() => setShowCreateForm(true)}
          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium"
        >
          Crea Nuovo Evento
        </button>
      </div>

      {/* Form di creazione evento */}
      {showCreateForm && (
        <div className="bg-white rounded-lg shadow-md p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Crea Nuovo Evento</h3>
          <form onSubmit={handleCreateEvent} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Titolo Evento *
              </label>
              <input
                type="text"
                value={newEvent.titolo}
                onChange={(e) => setNewEvent(prev => ({ ...prev, titolo: e.target.value }))}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Inserisci il titolo dell'evento"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Data Evento *
              </label>
              <input
                type="date"
                value={newEvent.event_date}
                onChange={(e) => setNewEvent(prev => ({ ...prev, event_date: e.target.value }))}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Immagine di Sfondo per PDF
              </label>
              <input
                type="file"
                accept="image/*"
                onChange={handleFileChange}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                disabled={uploading}
              />
              <div className="mt-2 text-xs text-gray-500">
                <p><strong>Dimensioni consigliate:</strong> 1080x1920px (9:16 verticale)</p>
                <p><strong>Dimensioni minime:</strong> 800x1200px</p>
                <p><strong>Ridimensionamento:</strong> Le immagini più grandi verranno ridimensionate automaticamente</p>
                <p><strong>Formati supportati:</strong> JPG, PNG, GIF (max 10MB)</p>
              </div>
              {uploading && (
                <p className="text-sm text-blue-600 mt-1">Caricamento in corso...</p>
              )}
            </div>

            {/* Anteprima immagine */}
            {previewImage && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Anteprima Immagine
                </label>
                <img
                  src={previewImage}
                  alt="Anteprima"
                  className="max-w-xs h-32 object-cover rounded-lg border border-gray-200"
                />
              </div>
            )}

            <div className="flex space-x-3">
              <button
                type="submit"
                disabled={createEventMutation.isLoading}
                className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-medium disabled:opacity-50"
              >
                {createEventMutation.isLoading ? 'Creazione...' : 'Crea Evento'}
              </button>
              <button
                type="button"
                onClick={() => {
                  setShowCreateForm(false);
                  setNewEvent({ titolo: '', event_date: '', background_image: null });
                  setPreviewImage(null);
                }}
                className="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md font-medium"
              >
                Annulla
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Lista eventi esistenti */}
      <div className="bg-white rounded-lg shadow-md overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-200">
          <h3 className="text-lg font-medium text-gray-900">Eventi Esistenti</h3>
        </div>
        <div className="divide-y divide-gray-200">
          {events?.map((event) => (
            <div key={event.id} className="p-6">
              <div className="flex items-center space-x-6">
                {/* Anteprima immagine PDF */}
                <div className="flex-shrink-0">
                  {event.background_image ? (
                    <div className="relative">
                      <img
                        src={getImageUrl(event.background_image)}
                        alt={`PDF Background - ${event.titolo}`}
                        className="h-24 w-32 object-cover rounded-lg border-2 border-gray-200"
                      />
                      <div className="absolute top-1 right-1 bg-green-500 text-white text-xs px-1 py-0.5 rounded">
                        PDF
                      </div>
                    </div>
                  ) : (
                    <div className="h-24 w-32 bg-gray-100 rounded-lg flex items-center justify-center border-2 border-dashed border-gray-300">
                      <div className="text-center">
                        <svg className="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span className="text-gray-400 text-xs mt-1">Nessuna immagine</span>
                      </div>
                    </div>
                  )}
                </div>

                {/* Dettagli evento */}
                <div className="flex-1 min-w-0">
                  <h4 className="text-lg font-medium text-gray-900 truncate">
                    {event.titolo}
                  </h4>
                  <p className="text-sm text-gray-500">
                    Data: {new Date(event.event_date).toLocaleDateString('it-IT')}
                  </p>
                  <p className="text-sm text-gray-500">
                    Stato: {event.chiuso ? 'Chiuso' : 'Aperto'}
                  </p>
                  {event.background_image && (
                    <p className="text-xs text-green-600 mt-1">
                      ✓ Immagine PDF configurata
                    </p>
                  )}
                </div>

                {/* Azioni */}
                <div className="flex-shrink-0 flex space-x-2">
                  {event.background_image && (
                    <button
                      onClick={() => generateTestPDF(event.id)}
                      className="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                      Test PDF
                    </button>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
        {(!events || events.length === 0) && (
          <div className="text-center py-8">
            <p className="text-gray-500">Nessun evento disponibile</p>
          </div>
        )}
      </div>

      {/* Informazioni */}
      <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
        <div className="flex">
          <div className="flex-shrink-0">
            <svg className="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
              <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
            </svg>
          </div>
          <div className="ml-3">
            <h3 className="text-sm font-medium text-blue-800">Come Funziona</h3>
            <div className="mt-2 text-sm text-blue-700">
              <p>1. <strong>Crea un evento</strong> con titolo e data</p>
              <p>2. <strong>Carica un'immagine</strong> che sarà usata come sfondo per i PDF</p>
              <p>3. <strong>Quando un utente si registra</strong>, il sistema genera automaticamente un PDF con:</p>
              <ul className="list-disc list-inside ml-4 mt-1">
                <li>L'immagine di sfondo che hai caricato</li>
                <li>QR Code univoco per l'utente</li>
                <li>Dettagli dell'evento e dell'utente</li>
                <li>Informazioni per l'ingresso</li>
              </ul>
              <p>4. <strong>Il PDF viene inviato via email</strong> all'utente registrato</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default EventCreationWithPDF;
