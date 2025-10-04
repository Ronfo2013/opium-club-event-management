import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from 'react-query';
import { toast } from 'react-hot-toast';
import axios from 'axios';
import LoadingSpinner from '../common/LoadingSpinner';
import { getImageUrl } from '../../utils/imageUtils';

const CarouselManagement = () => {
  const [showUploadForm, setShowUploadForm] = useState(false);
  const [uploading, setUploading] = useState(false);
  const queryClient = useQueryClient();

  // Fetch events for carousel
  const { data: events, isLoading, error } = useQuery('events', async () => {
    const response = await axios.get('http://localhost:8000/api/events.php');
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to fetch events');
    }
    return response.data.data;
  });

  // Update event mutation
  const updateEventMutation = useMutation(async ({ eventId, updates }) => {
    const response = await axios.post('http://localhost:8000/api/event-actions.php', {
      action: 'update',
      event_id: eventId,
      ...updates
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to update event');
    }
    return response.data;
  }, {
    onSuccess: () => {
      queryClient.invalidateQueries('events');
      toast.success('Evento aggiornato con successo');
    },
    onError: (error) => {
      toast.error(error.message || 'Errore durante l\'aggiornamento');
    }
  });

  const handleImageUpload = async (eventId, file) => {
    setUploading(true);
    try {
      const formData = new FormData();
      formData.append('image', file);
      formData.append('type', 'hero');
      formData.append('event_id', eventId);

      const response = await axios.post('http://localhost:8000/api/image-upload.php', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      if (response.data.success) {
        // Aggiorna l'evento con la nuova immagine
        updateEventMutation.mutate({
          eventId,
          updates: {
            background_image: response.data.data.path
          }
        });
        toast.success('Immagine caricata con successo');
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

  const handleFileChange = (eventId, e) => {
    const file = e.target.files[0];
    if (file) {
      handleImageUpload(eventId, file);
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
        <h2 className="text-2xl font-bold text-gray-900">Gestione Carosello</h2>
        <button
          onClick={() => setShowUploadForm(!showUploadForm)}
          className="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md font-medium"
        >
          {showUploadForm ? 'Nascondi Form' : 'Mostra Form Upload'}
        </button>
      </div>

      {/* Lista eventi con gestione immagini */}
      <div className="bg-white rounded-lg shadow-md overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-200">
          <h3 className="text-lg font-medium text-gray-900">Eventi e Immagini</h3>
        </div>
        <div className="divide-y divide-gray-200">
          {events?.map((event) => (
            <div key={event.id} className="p-6">
              <div className="flex items-center space-x-6">
                {/* Anteprima immagine */}
                <div className="flex-shrink-0">
                  {event.background_image ? (
                    <img
                      src={getImageUrl(event.background_image)}
                      alt={event.titolo}
                      className="h-20 w-32 object-cover rounded-lg"
                    />
                  ) : (
                    <div className="h-20 w-32 bg-gray-200 rounded-lg flex items-center justify-center">
                      <span className="text-gray-400 text-sm">Nessuna immagine</span>
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
                </div>

                {/* Upload immagine */}
                <div className="flex-shrink-0">
                  <label className="cursor-pointer">
                    <input
                      type="file"
                      accept="image/*"
                      onChange={(e) => handleFileChange(event.id, e)}
                      className="hidden"
                      disabled={uploading}
                    />
                    <span className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 disabled:opacity-50">
                      {uploading ? 'Caricamento...' : 'Cambia Immagine'}
                    </span>
                  </label>
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

      {/* Form upload batch */}
      {showUploadForm && (
        <div className="bg-white rounded-lg shadow-md p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Upload Immagini Multiple</h3>
          <div className="space-y-4">
            {events?.map((event) => (
              <div key={event.id} className="flex items-center space-x-4 p-3 border border-gray-200 rounded-lg">
                <div className="flex-1">
                  <h4 className="font-medium text-gray-900">{event.titolo}</h4>
                  <p className="text-sm text-gray-500">
                    {new Date(event.event_date).toLocaleDateString('it-IT')}
                  </p>
                </div>
                <label className="cursor-pointer">
                  <input
                    type="file"
                    accept="image/*"
                    onChange={(e) => handleFileChange(event.id, e)}
                    className="hidden"
                    disabled={uploading}
                  />
                  <span className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 disabled:opacity-50">
                    {uploading ? 'Caricamento...' : 'Scegli Immagine'}
                  </span>
                </label>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Anteprima carosello */}
      <div className="bg-white rounded-lg shadow-md p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Anteprima Carosello</h3>
        <div className="bg-gray-100 rounded-lg p-4">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {events?.filter(event => event.background_image).map((event) => (
              <div key={event.id} className="relative">
                <img
                  src={getImageUrl(event.background_image)}
                  alt={event.titolo}
                  className="w-full h-32 object-cover rounded-lg"
                />
                <div className="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white p-2 rounded-b-lg">
                  <h4 className="text-sm font-medium truncate">{event.titolo}</h4>
                  <p className="text-xs">
                    {new Date(event.event_date).toLocaleDateString('it-IT')}
                  </p>
                </div>
              </div>
            ))}
          </div>
          {(!events || events.filter(event => event.background_image).length === 0) && (
            <div className="text-center py-8">
              <p className="text-gray-500">Nessuna immagine disponibile per il carosello</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default CarouselManagement;
