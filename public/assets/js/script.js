document.getElementById('qrForm').addEventListener('submit', async function(e) {
    e.preventDefault();
  
    const formData = new FormData(this);
  
    try {
      const response = await fetch('./save-form', {
        method: 'POST',
        body: formData
      });
  
      if (!response.ok) {
        const errorText = await response.text();
        throw new Error(errorText);
      }
  
      const result = await response.json();
      if (result.success) {
        // Invia solo un alert, o mostra un messaggio di successo
        alert("Iscrizione completata! Controlla la tua email.");
        // Se vuoi resettare il form:
        this.reset();
      } else {
        alert('Error: ' + (result.error || 'Failed to process form'));
      }
    } catch (error) {
      alert('An error occurred while submitting the form: ' + error.message);
      console.error(error);
    }
  });