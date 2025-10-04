import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from 'react-query';
import { Toaster } from 'react-hot-toast';
import { AuthProvider } from './hooks/useAuth';
import Header from './components/layout/Header';
import Footer from './components/layout/Footer';
import HomePage from './pages/HomePage';
import LoginPage from './pages/LoginPage';
import AdminPage from './pages/AdminPage';
import QRScannerPage from './pages/QRScannerPage';
import ProtectedRoute from './components/auth/ProtectedRoute';
import PWAInstallBanner from './components/common/PWAInstallBanner';
import { useErrorTracking } from './hooks/useAnalytics';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

function App() {
  // Inizializza il tracking degli errori
  useErrorTracking();

  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <Router future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <div className="min-h-screen bg-gray-50">
            <Header />
                <main>
                  <Routes>
                    <Route path="/" element={<HomePage />} />
                    <Route path="/login" element={<LoginPage />} />
                    <Route 
                      path="/admin" 
                      element={
                        <ProtectedRoute>
                          <AdminPage />
                        </ProtectedRoute>
                      } 
                    />
                    <Route 
                      path="/admin/events" 
                      element={
                        <ProtectedRoute>
                          <AdminPage />
                        </ProtectedRoute>
                      } 
                    />
                    <Route 
                      path="/admin/users" 
                      element={
                        <ProtectedRoute>
                          <AdminPage />
                        </ProtectedRoute>
                      } 
                    />
                    <Route 
                      path="/admin/birthdays" 
                      element={
                        <ProtectedRoute>
                          <AdminPage />
                        </ProtectedRoute>
                      } 
                    />
                    <Route 
                      path="/admin/email-settings" 
                      element={
                        <ProtectedRoute>
                          <AdminPage />
                        </ProtectedRoute>
                      } 
                    />
                    <Route 
                      path="/admin/carousel" 
                      element={
                        <ProtectedRoute>
                          <AdminPage />
                        </ProtectedRoute>
                      } 
                    />
                    <Route 
                      path="/admin/pdf-images" 
                      element={
                        <ProtectedRoute>
                          <AdminPage />
                        </ProtectedRoute>
                      } 
                    />
                    <Route 
                      path="/admin/events-with-pdf" 
                      element={
                        <ProtectedRoute>
                          <AdminPage />
                        </ProtectedRoute>
                      } 
                    />
                    <Route 
                      path="/scanner" 
                      element={
                        <ProtectedRoute>
                          <QRScannerPage />
                        </ProtectedRoute>
                      } 
                    />
                  </Routes>
                </main>
            <Footer />
            <PWAInstallBanner />
            <Toaster position="top-right" />
          </div>
        </Router>
      </AuthProvider>
    </QueryClientProvider>
  );
}

export default App;
