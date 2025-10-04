import { useState, useEffect, createContext, useContext } from 'react';
import { auth } from '../api';

const AuthContext = createContext();

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  useEffect(() => {
    checkAuth();
  }, []);

  const checkAuth = async () => {
    try {
      const token = localStorage.getItem('auth_token');
      if (!token) {
        setIsLoading(false);
        return;
      }

      // Verifica il token con il backend PHP
      const response = await auth.verify();
      if (response.data.success) {
        setUser(response.data.user);
        setIsAuthenticated(true);
      } else {
        localStorage.removeItem('auth_token');
        setUser(null);
        setIsAuthenticated(false);
      }
    } catch (error) {
      console.error('Auth check failed:', error);
      localStorage.removeItem('auth_token');
      setUser(null);
      setIsAuthenticated(false);
    } finally {
      setIsLoading(false);
    }
  };

  const login = async (credentials) => {
    try {
      const response = await auth.login(credentials);
      if (response.data.success) {
        localStorage.setItem('auth_token', response.data.token);
        setUser(response.data.user);
        setIsAuthenticated(true);
      }
      return response.data;
    } catch (error) {
      return {
        success: false,
        message: 'Login failed',
      };
    }
  };

  const logout = async () => {
    try {
      await auth.logout();
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      localStorage.removeItem('auth_token');
      setUser(null);
      setIsAuthenticated(false);
    }
  };

  const value = {
    user,
    isLoading,
    isAuthenticated,
    login,
    logout,
    checkAuth,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};
