import React, { useState } from 'react';
import { useForm } from 'react-hook-form';
import toast from 'react-hot-toast';

const RegistrationForm = ({ events }) => {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const { register, handleSubmit, formState: { errors }, reset } = useForm();

  const onSubmit = async (data) => {
    setIsSubmitting(true);
    try {
      // Usa save_form.php che è il sistema originale completo
      // Converti i dati in FormData per compatibilità con $_POST
      const formData = new FormData();
      Object.keys(data).forEach(key => {
        formData.append(key, data[key]);
      });

      const response = await fetch('http://localhost:8000/save_form.php', {
        method: 'POST',
        body: formData,
      });

      const result = await response.json();
      
      if (result.success) {
        toast.success('Registrazione completata con successo! Controlla la tua email per il PDF con QR code.');
        reset();
      } else {
        toast.error(result.message || 'Errore durante la registrazione');
      }
    } catch (error) {
      console.error('Registration error:', error);
      toast.error('Errore di connessione. Riprova più tardi.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="bg-white rounded-lg shadow-lg p-8">
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Nome *
            </label>
            <input 
              type="text" 
              {...register('nome', { required: 'Il nome è obbligatorio' })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
              placeholder="Inserisci il tuo nome"
            />
            {errors.nome && (
              <p className="text-red-500 text-sm mt-1">{errors.nome.message}</p>
            )}
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Cognome *
            </label>
            <input 
              type="text" 
              {...register('cognome', { required: 'Il cognome è obbligatorio' })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
              placeholder="Inserisci il tuo cognome"
            />
            {errors.cognome && (
              <p className="text-red-500 text-sm mt-1">{errors.cognome.message}</p>
            )}
          </div>
        </div>
        
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Email *
          </label>
          <input 
            type="email" 
            {...register('email', { 
              required: 'L\'email è obbligatoria',
              pattern: {
                value: /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i,
                message: 'Email non valida'
              }
            })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
            placeholder="Inserisci la tua email"
          />
          {errors.email && (
            <p className="text-red-500 text-sm mt-1">{errors.email.message}</p>
          )}
        </div>
        
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Telefono *
          </label>
          <input 
            type="tel" 
            {...register('telefono', { required: 'Il telefono è obbligatorio' })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
            placeholder="Inserisci il tuo numero di telefono"
          />
          {errors.telefono && (
            <p className="text-red-500 text-sm mt-1">{errors.telefono.message}</p>
          )}
        </div>
        
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Data di nascita *
          </label>
          <input 
            type="date" 
            {...register('data_nascita', { required: 'La data di nascita è obbligatoria' })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
          />
          {errors.data_nascita && (
            <p className="text-red-500 text-sm mt-1">{errors.data_nascita.message}</p>
          )}
        </div>
        
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Evento *
          </label>
          <select 
            {...register('evento', { required: 'Seleziona un evento' })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
          >
            <option value="">Seleziona un evento</option>
            {events.map(event => (
              <option key={event.id} value={event.id}>
                {event.title} - {new Date(event.event_date).toLocaleDateString('it-IT')}
              </option>
            ))}
          </select>
          {errors.evento && (
            <p className="text-red-500 text-sm mt-1">{errors.evento.message}</p>
          )}
        </div>
        
        <div className="flex items-center">
          <input 
            type="checkbox" 
            {...register('privacy', { required: 'Devi accettare l\'informativa sulla privacy' })}
            className="mr-2"
          />
          <label className="text-sm text-gray-700">
            Ho letto e accetto l'informativa sulla privacy *
          </label>
        </div>
        {errors.privacy && (
          <p className="text-red-500 text-sm mt-1">{errors.privacy.message}</p>
        )}
        
        <button 
          type="submit"
          disabled={isSubmitting}
          className="w-full bg-gradient-to-r from-purple-500 to-blue-500 text-white py-3 px-6 rounded-md hover:from-purple-600 hover:to-blue-600 transition duration-300 font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {isSubmitting ? 'Invio in corso...' : 'Invia Registrazione'}
        </button>
      </form>
    </div>
  );
};

export default RegistrationForm;