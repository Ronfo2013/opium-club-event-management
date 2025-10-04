import axios from 'axios';

// Configurazione base per le chiamate API
const API_BASE_URL = 'http://localhost:8000';

const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Interceptor per gestire errori
api.interceptors.response.use(
  (response) => response,
  (error) => {
    console.error('API Error:', error);
    return Promise.reject(error);
  }
);

// Servizi API
export const apiService = {
  // Eventi
  getEvents: async () => {
    const response = await api.get('/api/events.php');
    return response.data;
  },

  // Registrazione utente
  registerUser: async (userData) => {
    const formData = new FormData();
    Object.keys(userData).forEach(key => {
      formData.append(key, userData[key]);
    });

    const response = await api.post('/save_form.php', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  // Validazione QR Code
  validateQR: async (token) => {
    const response = await api.get(`/api/validate-qr.php?token=${token}`);
    return response.data;
  },

  // Statistiche admin
  getStats: async () => {
    const response = await api.get('/api/stats.php');
    return response.data;
  },
};

export default apiService;





