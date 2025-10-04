import React from 'react';
import { Link } from 'react-router-dom';

const NotFoundPage = () => {
  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center">
      <div className="text-center">
        <h1 className="text-6xl font-bold text-gray-900 mb-4">404</h1>
        <h2 className="text-2xl font-semibold text-gray-700 mb-4">Pagina non trovata</h2>
        <p className="text-gray-600 mb-8">
          La pagina che stai cercando non esiste o è stata spostata.
        </p>
        <Link
          to="/"
          className="btn-primary"
        >
          Torna alla Home
        </Link>
      </div>
    </div>
  );
};

export default NotFoundPage;






