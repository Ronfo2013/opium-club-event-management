import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import UserManagement from '../components/admin/UserManagement';
import EventManagement from '../components/admin/EventManagement';
import BirthdayManagement from '../components/admin/BirthdayManagement';
import EmailSettings from '../components/admin/EmailSettings';
import CarouselManagement from '../components/admin/CarouselManagement';
import PDFImageManagement from '../components/admin/PDFImageManagement';
import EventCreationWithPDF from '../components/admin/EventCreationWithPDF';

const AdminPage = () => {
  const location = useLocation();
  const currentPath = location.pathname;
  // Renderizza contenuto diverso in base al percorso
  if (currentPath === '/admin/events') {
    return (
      <div className="min-h-screen bg-gray-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="mb-8">
            <Link to="/admin" className="text-blue-600 hover:text-blue-800 mb-4 inline-block">
              ← Torna al pannello admin
            </Link>
            <h1 className="text-3xl font-bold text-gray-900">Gestione Eventi</h1>
            <p className="text-gray-600 mt-2">Crea e gestisci gli eventi del club</p>
          </div>
          <EventManagement />
        </div>
      </div>
    );
  }

  if (currentPath === '/admin/users') {
    return (
      <div className="min-h-screen bg-gray-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="mb-8">
            <Link to="/admin" className="text-blue-600 hover:text-blue-800 mb-4 inline-block">
              ← Torna al pannello admin
            </Link>
            <h1 className="text-3xl font-bold text-gray-900">Gestione Utenti</h1>
            <p className="text-gray-600 mt-2">Visualizza e gestisci le registrazioni</p>
          </div>
          <UserManagement />
        </div>
      </div>
    );
  }

  if (currentPath === '/admin/birthdays') {
    return (
      <div className="min-h-screen bg-gray-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="mb-8">
            <Link to="/admin" className="text-blue-600 hover:text-blue-800 mb-4 inline-block">
              ← Torna al pannello admin
            </Link>
            <h1 className="text-3xl font-bold text-gray-900">Gestione Compleanni</h1>
            <p className="text-gray-600 mt-2">Gestisci i template e invia auguri automatici</p>
          </div>
          <BirthdayManagement />
        </div>
      </div>
    );
  }

  if (currentPath === '/admin/email-settings') {
    return (
      <div className="min-h-screen bg-gray-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="mb-8">
            <Link to="/admin" className="text-blue-600 hover:text-blue-800 mb-4 inline-block">
              ← Torna al pannello admin
            </Link>
            <h1 className="text-3xl font-bold text-gray-900">Impostazioni Email</h1>
            <p className="text-gray-600 mt-2">Configura i template delle email di conferma</p>
          </div>
          <EmailSettings />
        </div>
      </div>
    );
  }

  if (currentPath === '/admin/carousel') {
    return (
      <div className="min-h-screen bg-gray-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="mb-8">
            <Link to="/admin" className="text-blue-600 hover:text-blue-800 mb-4 inline-block">
              ← Torna al pannello admin
            </Link>
            <h1 className="text-3xl font-bold text-gray-900">Gestione Carosello</h1>
            <p className="text-gray-600 mt-2">Gestisci le immagini degli eventi per il carosello</p>
          </div>
          <CarouselManagement />
        </div>
      </div>
    );
  }

  if (currentPath === '/admin/pdf-images') {
    return (
      <div className="min-h-screen bg-gray-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="mb-8">
            <Link to="/admin" className="text-blue-600 hover:text-blue-800 mb-4 inline-block">
              ← Torna al pannello admin
            </Link>
            <h1 className="text-3xl font-bold text-gray-900">Gestione Immagini PDF</h1>
            <p className="text-gray-600 mt-2">Carica le immagini di sfondo per la generazione dei PDF degli eventi</p>
          </div>
          <PDFImageManagement />
        </div>
      </div>
    );
  }

  if (currentPath === '/admin/events-with-pdf') {
    return (
      <div className="min-h-screen bg-gray-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="mb-8">
            <Link to="/admin" className="text-blue-600 hover:text-blue-800 mb-4 inline-block">
              ← Torna al pannello admin
            </Link>
            <h1 className="text-3xl font-bold text-gray-900">Eventi con PDF Integrato</h1>
            <p className="text-gray-600 mt-2">Crea eventi e carica immagini per la generazione automatica dei PDF</p>
          </div>
          <EventCreationWithPDF />
        </div>
      </div>
    );
  }

  // Dashboard principale
  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Pannello Amministratore</h1>
          <p className="text-gray-600 mt-2">Gestisci eventi, utenti e sistema QR</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {/* Gestione Eventi */}
          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex items-center mb-4">
              <div className="bg-blue-100 p-3 rounded-lg">
                <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <h3 className="text-lg font-semibold text-gray-900 ml-3">Gestione Eventi</h3>
            </div>
            <p className="text-gray-600 mb-4">Crea e gestisci gli eventi del club</p>
            <Link
              to="/admin/events"
              className="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium"
            >
              Vai alla gestione eventi
              <svg className="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>

          {/* Gestione Utenti */}
          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex items-center mb-4">
              <div className="bg-green-100 p-3 rounded-lg">
                <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                </svg>
              </div>
              <h3 className="text-lg font-semibold text-gray-900 ml-3">Gestione Utenti</h3>
            </div>
            <p className="text-gray-600 mb-4">Visualizza e gestisci le registrazioni</p>
            <Link
              to="/admin/users"
              className="inline-flex items-center text-green-600 hover:text-green-800 font-medium"
            >
              Vai alla gestione utenti
              <svg className="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>

          {/* Gestione Compleanni */}
          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex items-center mb-4">
              <div className="bg-pink-100 p-3 rounded-lg">
                <svg className="w-6 h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
              </div>
              <h3 className="text-lg font-semibold text-gray-900 ml-3">Gestione Compleanni</h3>
            </div>
            <p className="text-gray-600 mb-4">Gestisci i template e invia auguri automatici</p>
            <Link
              to="/admin/birthdays"
              className="inline-flex items-center text-pink-600 hover:text-pink-800 font-medium"
            >
              Vai alla gestione compleanni
              <svg className="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>

          {/* Scanner QR */}
          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex items-center mb-4">
              <div className="bg-purple-100 p-3 rounded-lg">
                <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                </svg>
              </div>
              <h3 className="text-lg font-semibold text-gray-900 ml-3">Scanner QR</h3>
            </div>
            <p className="text-gray-600 mb-4">Scansiona i QR code per validare gli ingressi</p>
            <Link
              to="/scanner"
              className="inline-flex items-center text-purple-600 hover:text-purple-800 font-medium"
            >
              Apri scanner QR
              <svg className="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>

          {/* Statistiche */}
          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex items-center mb-4">
              <div className="bg-yellow-100 p-3 rounded-lg">
                <svg className="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
              <h3 className="text-lg font-semibold text-gray-900 ml-3">Statistiche</h3>
            </div>
            <p className="text-gray-600 mb-4">Visualizza le statistiche degli eventi</p>
            <Link
              to="/admin/stats"
              className="inline-flex items-center text-yellow-600 hover:text-yellow-800 font-medium"
            >
              Vedi statistiche
              <svg className="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>

          {/* Impostazioni Email */}
          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex items-center mb-4">
              <div className="bg-indigo-100 p-3 rounded-lg">
                <svg className="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <h3 className="text-lg font-semibold text-gray-900 ml-3">Impostazioni Email</h3>
            </div>
            <p className="text-gray-600 mb-4">Configura i template delle email di conferma</p>
            <Link
              to="/admin/email-settings"
              className="inline-flex items-center text-indigo-600 hover:text-indigo-800 font-medium"
            >
              Vai alle impostazioni email
              <svg className="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>

          {/* Gestione Carosello */}
          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex items-center mb-4">
              <div className="bg-orange-100 p-3 rounded-lg">
                <svg className="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <h3 className="text-lg font-semibold text-gray-900 ml-3">Gestione Carosello</h3>
            </div>
            <p className="text-gray-600 mb-4">Gestisci le immagini degli eventi per il carosello</p>
            <Link
              to="/admin/carousel"
              className="inline-flex items-center text-orange-600 hover:text-orange-800 font-medium"
            >
              Vai alla gestione carosello
              <svg className="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>

          {/* Eventi con PDF Integrato */}
          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex items-center mb-4">
              <div className="bg-purple-100 p-3 rounded-lg">
                <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
              </div>
              <h3 className="text-lg font-semibold text-gray-900 ml-3">Eventi con PDF</h3>
            </div>
            <p className="text-gray-600 mb-4">Crea eventi e carica immagini per PDF automatici</p>
            <Link
              to="/admin/events-with-pdf"
              className="inline-flex items-center text-purple-600 hover:text-purple-800 font-medium"
            >
              Vai alla gestione integrata
              <svg className="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>

          {/* Gestione Immagini PDF */}
          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex items-center mb-4">
              <div className="bg-teal-100 p-3 rounded-lg">
                <svg className="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <h3 className="text-lg font-semibold text-gray-900 ml-3">Immagini PDF</h3>
            </div>
            <p className="text-gray-600 mb-4">Carica le immagini di sfondo per i PDF degli eventi</p>
            <Link
              to="/admin/pdf-images"
              className="inline-flex items-center text-teal-600 hover:text-teal-800 font-medium"
            >
              Vai alla gestione PDF
              <svg className="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>
          
          {/* Accesso Backend PHP */}
          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex items-center mb-4">
              <div className="bg-red-100 p-3 rounded-lg">
                <svg className="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
              </div>
              <h3 className="text-lg font-semibold text-gray-900 ml-3">Backend PHP</h3>
            </div>
            <p className="text-gray-600 mb-4">Accedi al pannello amministrativo PHP</p>
            <a
              href="http://localhost:8000/admin"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center text-red-600 hover:text-red-800 font-medium"
            >
              Apri backend PHP
              <svg className="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
          </div>
        </div>
      </div>
    </div>
  );
};

export default AdminPage;