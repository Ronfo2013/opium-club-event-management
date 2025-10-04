import React, { useState, useEffect } from 'react';
import { Download, X, Smartphone, Monitor } from 'lucide-react';
import { usePWA } from '../../hooks/usePWA';

const PWAInstallBanner = () => {
  const { isInstallable, isInstalled, installApp } = usePWA();
  const [isDismissed, setIsDismissed] = useState(false);

  // Controlla se il banner è stato dismissato in precedenza
  useEffect(() => {
    const dismissed = localStorage.getItem('pwa-banner-dismissed');
    if (dismissed === 'true') {
      setIsDismissed(true);
    }
  }, []);

  const handleInstall = async () => {
    await installApp();
    setIsDismissed(true);
  };

  const handleDismiss = () => {
    setIsDismissed(true);
    // Salva la preferenza nel localStorage
    localStorage.setItem('pwa-banner-dismissed', 'true');
  };

  // Non mostrare il banner se l'app è già installata o se è stato dismissato
  if (isInstalled || isDismissed || !isInstallable) {
    return null;
  }

  return (
    <div className="fixed bottom-4 left-4 right-4 z-50 md:left-auto md:right-4 md:max-w-sm">
      <div className="bg-white rounded-lg shadow-lg border border-gray-200 p-4">
        <div className="flex items-start justify-between">
          <div className="flex items-start space-x-3">
            <div className="flex-shrink-0">
              <div className="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-500 rounded-lg flex items-center justify-center">
                <Smartphone className="w-5 h-5 text-white" />
              </div>
            </div>
            <div className="flex-1 min-w-0">
              <h3 className="text-sm font-semibold text-gray-900">
                Installa l'app
              </h3>
              <p className="text-xs text-gray-600 mt-1">
                Aggiungi Opium Club alla tua home screen per un accesso più veloce
              </p>
            </div>
          </div>
          <button
            onClick={handleDismiss}
            className="flex-shrink-0 ml-2 text-gray-400 hover:text-gray-600 transition-colors"
          >
            <X className="w-4 h-4" />
          </button>
        </div>
        
        <div className="mt-3 flex space-x-2">
          <button
            onClick={handleInstall}
            className="flex-1 bg-gradient-to-r from-purple-500 to-pink-500 text-white text-xs font-medium py-2 px-3 rounded-md hover:from-purple-600 hover:to-pink-600 transition-all duration-200 flex items-center justify-center space-x-1"
          >
            <Download className="w-3 h-3" />
            <span>Installa</span>
          </button>
          <button
            onClick={handleDismiss}
            className="px-3 py-2 text-xs text-gray-600 hover:text-gray-800 transition-colors"
          >
            Non ora
          </button>
        </div>

        <div className="mt-2 text-xs text-gray-500">
          <div className="flex items-center space-x-1">
            <Monitor className="w-3 h-3" />
            <span>Disponibile anche su desktop</span>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PWAInstallBanner;
