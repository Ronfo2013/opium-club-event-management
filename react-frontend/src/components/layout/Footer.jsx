import React from 'react';

const Footer = () => {
  return (
    <footer className="bg-gray-800 text-white">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div>
            <h3 className="text-lg font-semibold mb-4">Opium Club</h3>
            <p className="text-gray-300">
              Il miglior club di Pordenone per eventi esclusivi e intrattenimento di qualità.
            </p>
          </div>
          
          <div>
            <h3 className="text-lg font-semibold mb-4">Contatti</h3>
            <div className="space-y-2 text-gray-300">
              <p>📍 Pordenone, Italia</p>
              <p>📧 info@opiumpordenone.com</p>
              <p>📱 +39 123 456 7890</p>
            </div>
          </div>
          
          <div>
            <h3 className="text-lg font-semibold mb-4">Sistema QR</h3>
            <p className="text-gray-300">
              Gestione eventi con QR code per un accesso rapido e sicuro.
            </p>
          </div>
        </div>
        
        <div className="border-t border-gray-700 mt-8 pt-8 text-center text-gray-300">
          <p>&copy; 2025 Opium Club Pordenone. Tutti i diritti riservati.</p>
        </div>
      </div>
    </footer>
  );
};

export default Footer;






