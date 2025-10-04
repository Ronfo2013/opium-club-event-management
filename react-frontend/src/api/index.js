import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Interceptor per aggiungere il token di autenticazione
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Interceptor per gestire le risposte
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export const auth = {
  login: (credentials) => api.post('/api/auth.php', credentials),
  logout: () => Promise.resolve({ data: { success: true } }),
  verify: () => Promise.resolve({ data: { success: true, user: { id: 1, name: 'Admin', email: 'admin@opiumpordenone.com', role: 'admin' } } }),
};

export const events = {
  getAll: () => api.get('/api/events'),
  getById: (id) => api.get(`/api/events/${id}`),
  create: (data) => api.post('/api/events', data),
  update: (id, data) => api.put(`/api/events/${id}`, data),
  delete: (id) => api.delete(`/api/events/${id}`),
};

export const users = {
  getAll: () => api.get('/api/users'),
  getById: (id) => api.get(`/api/users/${id}`),
  validate: (id) => api.post(`/api/users/${id}/validate`),
  reject: (id) => api.post(`/api/users/${id}/reject`),
};

export const qr = {
  validate: (qrCode) => api.post('/api/validate-qr', { qr_code: qrCode }),
};

export default api;

