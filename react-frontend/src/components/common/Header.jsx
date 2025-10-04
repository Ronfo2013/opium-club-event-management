import React from 'react';
import { Link } from 'react-router-dom';

const Header = () => {
  return (
    <header className="bg-white shadow-sm">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16">
          <div className="flex-shrink-0 flex items-center">
            <Link to="/" className="text-2xl font-bold text-purple-700">
              Opium Club
            </Link>
          </div>
          <nav className="flex items-center space-x-4">
            <Link to="/" className="text-gray-600 hover:text-purple-700 px-3 py-2 rounded-md text-sm font-medium">
              Home
            </Link>
            {/* Aggiungi altri link qui se necessario, ad es. /events */}
            <Link to="/login" className="bg-purple-600 text-white px-3 py-2 rounded-md text-sm font-medium hover:bg-purple-700">
              Login Admin
            </Link>
          </nav>
        </div>
      </div>
    </header>
  );
};

export default Header;






