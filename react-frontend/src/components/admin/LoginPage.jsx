import React, { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useAuth } from '../../hooks/useAuth';
import toast from 'react-hot-toast';

const LoginPage = () => {
  const [isLoading, setIsLoading] = useState(false);
  const { login } = useAuth();
  const { register, handleSubmit, formState: { errors } } = useForm();

  const onSubmit = async (data) => {
    setIsLoading(true);
    
    try {
      const result = await login(data);
      if (result.success) {
        toast.success('Login effettuato con successo!');
      } else {
        toast.error(result.message);
      }
    } catch (error) {
      toast.error('Errore durante il login');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center">
      <div className="max-w-md w-full space-y-8">
        <div className="text-center">
          <h2 className="text-3xl font-bold text-gray-900">
            Accesso Amministrazione
          </h2>
          <p className="mt-2 text-sm text-gray-600">
            Inserisci le tue credenziali per accedere al pannello admin
          </p>
        </div>
        
        <div className="card">
          <div className="card-body">
            <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
              <div>
                <label className="form-label">Email</label>
                <input
                  type="email"
                  {...register('email', { 
                    required: 'L\'email è obbligatoria',
                    pattern: {
                      value: /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i,
                      message: 'Email non valida'
                    }
                  })}
                  className={`input ${errors.email ? 'input-error' : ''}`}
                  placeholder="admin@opiumpordenone.com"
                />
                {errors.email && (
                  <p className="form-error">{errors.email.message}</p>
                )}
              </div>

              <div>
                <label className="form-label">Password</label>
                <input
                  type="password"
                  {...register('password', { 
                    required: 'La password è obbligatoria',
                    minLength: { value: 3, message: 'Password troppo corta' }
                  })}
                  className={`input ${errors.password ? 'input-error' : ''}`}
                  placeholder="Inserisci la password"
                  autocomplete="current-password"
                />
                {errors.password && (
                  <p className="form-error">{errors.password.message}</p>
                )}
              </div>

              <button
                type="submit"
                disabled={isLoading}
                className="btn-primary w-full"
              >
                {isLoading ? (
                  <>
                    <div className="loading-spinner mr-2"></div>
                    Accesso in corso...
                  </>
                ) : (
                  'Accedi'
                )}
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  );
};

export default LoginPage;






