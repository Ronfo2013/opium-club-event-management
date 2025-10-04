import React from 'react';

const Footer = () => {
  return (
    <footer className="bg-black text-gray-200 py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {/* Company Info */}
          <div>
            <h3 className="text-lg font-semibold text-white mb-4">Opium Club Pordenone</h3>
            <p className="text-sm text-gray-400 mb-4">
              Il tuo locale di fiducia per eventi indimenticabili a Pordenone.
            </p>
            <div className="text-sm text-gray-400">
              <p>Via Risiera 3, Zoppola - Pordenone</p>
              <p>P.IVA 02721250302</p>
            </div>
          </div>

          {/* Quick Links */}
          <div>
            <h3 className="text-lg font-semibold text-white mb-4">Link Utili</h3>
            <ul className="space-y-2">
              <li>
                <a 
                  href="https://www.opiumclubpordenone.com" 
                  target="_blank" 
                  rel="noopener noreferrer"
                  className="text-sm text-gray-400 hover:text-white transition-colors duration-300"
                >
                  Sito Web Ufficiale
                </a>
              </li>
              <li>
                <a 
                  href="mailto:info@opiumpordenone.com"
                  className="text-sm text-gray-400 hover:text-white transition-colors duration-300"
                >
                  Contattaci
                </a>
              </li>
              <li>
                <a 
                  href="https://www.benhanced.it" 
                  target="_blank" 
                  rel="noopener noreferrer"
                  className="text-sm text-gray-400 hover:text-white transition-colors duration-300"
                >
                  Sviluppato da Benhanced
                </a>
              </li>
            </ul>
          </div>

          {/* Contact Info */}
          <div>
            <h3 className="text-lg font-semibold text-white mb-4">Contatti</h3>
            <div className="text-sm text-gray-400 space-y-2">
              <p>
                <span className="text-white">Email:</span> info@opiumpordenone.com
              </p>
              <p>
                <span className="text-white">Indirizzo:</span> Via Risiera 3, Zoppola - Pordenone
              </p>
              <p>
                <span className="text-white">P.IVA:</span> 02721250302
              </p>
            </div>
          </div>
        </div>

        <div className="border-t border-gray-800 mt-8 pt-8">
          <div className="flex flex-col md:flex-row justify-between items-center">
            <p className="text-sm text-gray-400">
              © {new Date().getFullYear()} Opium Club Pordenone. Tutti i diritti riservati.
            </p>
            <p className="text-sm text-gray-400 mt-2 md:mt-0">
              Sistema QR Code sviluppato da{' '}
              <a 
                href="https://www.benhanced.it" 
                target="_blank" 
                rel="noopener noreferrer"
                className="text-purple-400 hover:text-purple-300 transition-colors duration-300"
              >
                Benhanced
              </a>
            </p>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;






