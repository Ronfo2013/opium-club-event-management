import axios from 'axios';

// Create axios instance
export const apiClient = axios.create({
  baseURL: process.env.REACT_APP_API_URL || 'http://localhost:8000/api/v1',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request interceptor
apiClient.interceptors.request.use(
  (config) => {
    // Add auth token if available
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor
apiClient.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    if (error.response?.status === 401) {
      // Unauthorized - clear token and redirect to login
      localStorage.removeItem('auth_token');
      window.location.href = '/admin/login';
    }
    
    return Promise.reject(error);
  }
);

// API endpoints
export const api = {
  // Auth
  auth: {
    login: (credentials) => apiClient.post('/auth/login', credentials),
    logout: () => apiClient.post('/auth/logout'),
    me: () => apiClient.get('/auth/me'),
  },

  // Events
  events: {
    list: (params = {}) => apiClient.get('/events', { params }),
    get: (id) => apiClient.get(`/events/${id}`),
    create: (data) => apiClient.post('/events', data),
    update: (id, data) => apiClient.put(`/events/${id}`, data),
    delete: (id) => apiClient.delete(`/events/${id}`),
    close: (id) => apiClient.post(`/events/${id}/close`),
    reopen: (id) => apiClient.post(`/events/${id}/reopen`),
    stats: (id) => apiClient.get(`/events/${id}/stats`),
    sendEmails: (id) => apiClient.post(`/events/${id}/send-emails`),
  },

  // Users
  users: {
    register: (data) => apiClient.post('/users/register', data),
    list: (params = {}) => apiClient.get('/users', { params }),
    get: (id) => apiClient.get(`/users/${id}`),
    update: (id, data) => apiClient.put(`/users/${id}`, data),
    delete: (id) => apiClient.delete(`/users/${id}`),
    search: (query) => apiClient.get('/users/search', { params: { q: query } }),
    stats: (params = {}) => apiClient.get('/users/stats', { params }),
    resendEmail: (id) => apiClient.post(`/users/${id}/resend-email`),
  },

  // QR Codes
  qr: {
    validate: (token) => apiClient.get(`/qr/validate/${token}`),
    markValidated: (token) => apiClient.post(`/qr/validate/${token}`),
    getImage: (token) => apiClient.get(`/qr/image/${token}`),
    regenerate: (token) => apiClient.post(`/qr/regenerate/${token}`),
  },

  // Admin
  admin: {
    stats: () => apiClient.get('/admin/stats'),
    health: () => apiClient.get('/admin/health'),
    emailTexts: {
      get: () => apiClient.get('/admin/email-texts'),
      update: (data) => apiClient.put('/admin/email-texts', data),
    },
    birthday: {
      sendEmails: () => apiClient.post('/admin/birthday-emails'),
      upcoming: (days = 7) => apiClient.get('/admin/upcoming-birthdays', { params: { days } }),
    },
    export: (type, params = {}) => apiClient.get('/admin/export', { params: { type, ...params } }),
  },

  // Health check
  health: () => apiClient.get('/health'),
};

export default api;






