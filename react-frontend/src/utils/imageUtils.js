// Utility per gestire le immagini con CORS
export const getImageUrl = (imagePath) => {
  if (!imagePath) return null;
  
  // Se è già un URL completo, usalo così com'è
  if (imagePath.startsWith('http')) {
    return imagePath;
  }
  
  // Se inizia con /uploads/, usa il proxy CORS
  if (imagePath.startsWith('/uploads/')) {
    const fileName = imagePath.replace('/uploads/', '');
    return `http://localhost:8000/image-proxy.php?file=${encodeURIComponent(fileName)}`;
  }
  
  // Altrimenti, costruisci l'URL diretto
  return `http://localhost:8000${imagePath}`;
};

// Utility per ottenere l'URL diretto (senza proxy) se necessario
export const getDirectImageUrl = (imagePath) => {
  if (!imagePath) return null;
  
  // Se è già un URL completo, usalo così com'è
  if (imagePath.startsWith('http')) {
    return imagePath;
  }
  
  // Costruisci l'URL diretto
  return `http://localhost:8000${imagePath}`;
};





