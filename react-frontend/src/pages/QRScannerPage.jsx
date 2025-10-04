import React, { useState } from 'react';
import toast from 'react-hot-toast';

const QRScannerPage = () => {
  const [qrCode, setQrCode] = useState('');
  const [isScanning, setIsScanning] = useState(false);
  const [scanResult, setScanResult] = useState(null);

  const handleScan = async () => {
    if (!qrCode.trim()) {
      toast.error('Inserisci un codice QR');
      return;
    }

    setIsScanning(true);
    try {
      const response = await fetch('http://localhost:8000/api/validate-qr', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ qr_code: qrCode }),
      });

      const result = await response.json();
      
      if (result.success) {
        setScanResult(result.data);
        toast.success('QR Code validato con successo!');
      } else {
        setScanResult(null);
        toast.error(result.message || 'QR Code non valido');
      }
    } catch (error) {
      console.error('Scan error:', error);
      toast.error('Errore durante la validazione');
    } finally {
      setIsScanning(false);
    }
  };

  const clearResult = () => {
    setScanResult(null);
    setQrCode('');
  };

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Scanner QR Code</h1>
          <p className="text-gray-600 mt-2">Scansiona i QR code per validare gli ingressi</p>
        </div>

        <div className="bg-white rounded-lg shadow-lg p-8">
          <div className="space-y-6">
            {/* Input QR Code */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Inserisci o scansiona il QR Code
              </label>
              <div className="flex space-x-4">
                <input
                  type="text"
                  value={qrCode}
                  onChange={(e) => setQrCode(e.target.value)}
                  placeholder="Incolla qui il codice QR o usa la fotocamera"
                  className="flex-1 px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                />
                <button
                  onClick={handleScan}
                  disabled={isScanning}
                  className="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-md font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isScanning ? 'Scansionando...' : 'Scansiona'}
                </button>
              </div>
            </div>

            {/* Risultato Scansione */}
            {scanResult && (
              <div className="bg-green-50 border border-green-200 rounded-lg p-6">
                <div className="flex items-center mb-4">
                  <div className="bg-green-100 p-2 rounded-full">
                    <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <h3 className="text-lg font-semibold text-green-800 ml-3">QR Code Valido</h3>
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm text-gray-600">Nome</p>
                    <p className="font-medium text-gray-900">{scanResult.nome} {scanResult.cognome}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Email</p>
                    <p className="font-medium text-gray-900">{scanResult.email}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Evento</p>
                    <p className="font-medium text-gray-900">{scanResult.evento_title}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Data Registrazione</p>
                    <p className="font-medium text-gray-900">
                      {new Date(scanResult.created_at).toLocaleDateString('it-IT')}
                    </p>
                  </div>
                </div>

                <div className="mt-4 pt-4 border-t border-green-200">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center">
                      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                        scanResult.validato 
                          ? 'bg-red-100 text-red-800' 
                          : 'bg-green-100 text-green-800'
                      }`}>
                        {scanResult.validato ? 'Già Validato' : 'Disponibile'}
                      </span>
                    </div>
                    <button
                      onClick={clearResult}
                      className="text-gray-500 hover:text-gray-700 text-sm font-medium"
                    >
                      Chiudi
                    </button>
                  </div>
                </div>
              </div>
            )}

            {/* Istruzioni */}
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
              <h3 className="text-lg font-semibold text-blue-800 mb-3">Come usare lo scanner</h3>
              <ul className="space-y-2 text-blue-700">
                <li className="flex items-start">
                  <span className="flex-shrink-0 w-6 h-6 bg-blue-200 rounded-full flex items-center justify-center text-xs font-medium text-blue-800 mr-3 mt-0.5">1</span>
                  Posiziona il QR code davanti alla fotocamera o incollalo nel campo di testo
                </li>
                <li className="flex items-start">
                  <span className="flex-shrink-0 w-6 h-6 bg-blue-200 rounded-full flex items-center justify-center text-xs font-medium text-blue-800 mr-3 mt-0.5">2</span>
                  Clicca su "Scansiona" per validare il codice
                </li>
                <li className="flex items-start">
                  <span className="flex-shrink-0 w-6 h-6 bg-blue-200 rounded-full flex items-center justify-center text-xs font-medium text-blue-800 mr-3 mt-0.5">3</span>
                  Verifica le informazioni dell'utente e conferma l'ingresso
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default QRScannerPage;