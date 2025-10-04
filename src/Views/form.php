<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Opium Club - Pordenone</title>
  <!-- Importa Tailwind CSS v3.3.1 da CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Imposta le variabili di colore ispirate a MrCharlie */
    :root {
      --color-primary: 87 154 255;
      --color-primary-dark: 62 125 220;
      --color-secondary: 86 86 86;
      --color-secondary-dark: 70 70 70;
      --color-accent: 87 154 255;
      --color-accent-dark: 62 125 220;
    }
    /* Animazione per lo sfondo dell'hero */
    .animated-bg {
      background-size: 200% 200%;
      animation: bgAnimation 20s ease-in-out infinite;
    }

    @keyframes bgAnimation {
      0%, 100% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
    }

    /* Fade-in per contenuti */
    .fade-in {
      opacity: 0;
      animation: fadeIn 1s forwards;
    }
    @keyframes fadeIn {
      to { opacity: 1; }
    }
    [x-cloak] { display: none; }
    

  </style>
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#579aff">
</head>
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js')
    .then(reg => console.log('Service Worker registrato!', reg))
    .catch(err => console.error('Service Worker errore:', err));
}
</script>
<body class="flex flex-col min-h-screen bg-gray-50" x-data x-cloak>

  <!-- Navbar -->
  <nav class="bg-black text-gray-100 shadow fade-in">
    <div class="container mx-auto px-4 py-4 flex justify-between items-center">
      <div class="flex items-center">
        <!-- Logo: assicurati che 003.png sia presente nella cartella corretta -->
        <img src="/003.png" alt="Logo Opium Club Pordenone" class="w-30 h-10 mr-2" />
      </div>
      <!-- Desktop Menu -->
      <ul class="hidden md:flex space-x-6">
        <li><a href="https://www.opiumclubpordenone.com" class="transition-colors duration-300 hover:text-indigo-300">Home</a></li>
      </ul>
      <!-- Hamburger Menu per Mobile -->
      <button id="menu-toggle" class="md:hidden focus:outline-none transform transition duration-300 hover:scale-110 z-50">
        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <line x1="4" y1="6" x2="20" y2="6" />
          <line x1="4" y1="12" x2="20" y2="12" />
          <line x1="4" y1="18" x2="20" y2="18" />
        </svg>
      </button>
    </div>
    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-black">
      <a href="https://www.opiumclubpordenone.com" class="block px-4 py-2 text-gray-200 transition-colors hover:bg-gray-800">Home</a>
    </div>
  </nav>
  
  <!-- Hero Section -->
  <header class="relative w-full overflow-hidden">
<?php
// Lettura immagini hero dal JSON e stato chiuso evento
$images = [];

// Costruisci mappa data evento => chiuso
$eventStatusByDate = [];
if (isset($events) && is_array($events)) {
  foreach ($events as $event) {
    $event_date = isset($event['event_date']) ? $event['event_date'] : (isset($event['date']) ? $event['date'] : null);
    if ($event_date) {
      $eventStatusByDate[$event_date] = isset($event['chiuso']) ? $event['chiuso'] : 0;
    }
  }
}

// Preferisci GCS se configurato e bucket impostato
$gcsEnabled = isset($config['gcs']['enabled']) && $config['gcs']['enabled'] && !empty($config['gcs']['bucket']);
if ($gcsEnabled) {
    $bucket = $config['gcs']['bucket'];
    $jsonUrl = 'https://storage.googleapis.com/' . $bucket . '/hero_images.json';
    $jsonContent = @file_get_contents($jsonUrl);
    if ($jsonContent !== false) {
        $heroData = json_decode($jsonContent, true);
        if (is_array($heroData)) {
            $today = new DateTime();
            foreach ($heroData as $item) {
                if (!isset($item['expires'])) continue;
                $expires = new DateTime($item['expires']);
                if ($expires >= $today) {
                    $src = isset($item['url']) && filter_var($item['url'], FILTER_VALIDATE_URL)
                        ? $item['url']
                        : ('https://storage.googleapis.com/' . $bucket . '/' . (($config['gcs']['hero_prefix'] ?? 'hero_images/') . (isset($item['filename']) ? $item['filename'] : '')));
                    $images[] = [
                        'src' => $src,
                        'chiuso' => isset($eventStatusByDate[$item['expires']]) ? $eventStatusByDate[$item['expires']] : 0
                    ];
                }
            }
        }
    }
}

// Fallback: file locale in public/
if (empty($images)) {
    $uploadUrl = '/hero_images/';
    $heroJsonPath = __DIR__ . '/../../public/hero_images.json';
    if (file_exists($heroJsonPath)) {
        $heroData = json_decode(file_get_contents($heroJsonPath), true);
        $today = new DateTime();
        foreach ($heroData as $item) {
            if (!isset($item['filename'], $item['expires'])) continue;
            $expires = new DateTime($item['expires']);
            if ($expires >= $today) {
                $images[] = [
                    'src' => $uploadUrl . $item['filename'],
                    'chiuso' => isset($eventStatusByDate[$item['expires']]) ? $eventStatusByDate[$item['expires']] : 0
                ];
            }
        }
    }
}
?>
    <!-- Alpine.js Carousel - versione avanzata con overlay se chiuso -->
    <div x-data="carousel()" x-init="init()" class="relative w-full aspect-[16/9] overflow-hidden bg-black">
      <div class="w-full flex transition-transform duration-700"
           :style="'transform: translateX(-' + (current * 100) + '%)'">
        <template x-for="(image, idx) in images" :key="idx">
          <div class="relative h-full w-full flex-shrink-0" style="min-width:100%;">
            <img :src="image.src" :alt="'Slide ' + idx" class="h-full w-full object-cover" />
            <template x-if="image.chiuso == 1">
              <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="bg-red-700/90 w-full py-6 text-center text-white font-extrabold text-3xl uppercase tracking-widest shadow-xl rounded">
                  OMAGGI TERMINATI
                </div>
              </div>
            </template>
          </div>
        </template>
      </div>
      <!-- Indicatori -->
      <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex space-x-2">
        <template x-for="(image, index) in images" :key="index">
          <button
            class="w-3 h-3 rounded-full"
            :class="current === index ? 'bg-white' : 'bg-gray-400'"
            @click="current = index"
          ></button>
        </template>
      </div>
    </div>

    <script>
      function carousel() {
        return {
          images: <?php echo json_encode($images); ?>,
          current: 0,
          interval: null,
          init() {
            if (this.images.length > 1) {
              this.interval = setInterval(() => {
                this.current = (this.current + 1) % this.images.length;
              }, 4000);
            }
          }
        }
      }
    </script>
  </header>
  
  <!-- Sezione del Form -->
  <main class="container my-4">
    <section id="form-section" class="flex flex-col items-center justify-center mx-auto">
      <!-- Il contenuto e la logica del form devono restare invariati -->
      <!-- Solo l'aspetto grafico viene aggiornato -->
      <div class="w-full max-w-lg bg-white shadow-lg rounded-[15px] p-6 fade-in" style="animation-delay: 2s;">
        <div id="form-container">
          <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Richiedi qui l'ingresso omaggio donna</h2>
          <form id="qrForm" action="/save-form" method="POST" class="space-y-4">
            <div>
              <label for="nome" class="block text-gray-700">Nome</label>
              <input type="text" id="nome" name="nome" placeholder="Inserisci il tuo nome" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 transition" />
            </div>
            <div>
              <label for="cognome" class="block text-gray-700">Cognome</label>
              <input type="text" id="cognome" name="cognome" placeholder="Inserisci il tuo cognome" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 transition" />
            </div>
            <div>
              <label for="email" class="block text-gray-700">Email</label>
              <input type="email" id="email" name="email" placeholder="Inserisci la tua email" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 transition" />
            </div>
            <div>
              <label for="telefono" class="block text-gray-700">Numero di telefono</label>
              <input type="tel" id="telefono" name="telefono" placeholder="Inserisci il tuo numero di telefono" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 transition" />
            </div>
            <div>
              <label for="data-nascita" class="block text-gray-700">Data di nascita</label>
              <input type="date" id="data-nascita" name="data-nascita" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 transition" />
            </div>
            <div>
              <label for="evento" class="block text-gray-700">Seleziona Evento</label>
              <select id="evento" name="evento" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 transition">
                <option value="" disabled selected>Seleziona un evento</option>
                <?php
                  // Debug: verifica se $events esiste
                  if (!isset($events)) {
                    echo '<option disabled>ERRORE: Variabile $events non definita</option>';
                    echo '<script>console.log("[DEBUG form.php] Variabile $events non definita");</script>';
                  } elseif (empty($events)) {
                    echo '<option disabled>Nessun evento disponibile (array vuoto)</option>';
                    echo '<script>console.log("[DEBUG form.php] Array $events vuoto");</script>';
                  } else {
                    echo '<script>console.log("[DEBUG form.php] Eventi trovati: ' . count($events) . '");</script>';
                    echo '<script>console.log("[DEBUG form.php] Eventi:", ' . json_encode($events) . ');</script>';
                  }
                  
                  $oggi = date('Y-m-d');
                  if (isset($events) && is_array($events)) {
                    foreach ($events as $event):
                    // Support both 'event_date' and 'date' keys
                    $event_date = isset($event['event_date']) ? $event['event_date'] : (isset($event['date']) ? $event['date'] : null);
                    if (!$event_date) continue;
                    // Convert to Y-m-d if needed
                    $event_date_db = date('Y-m-d', strtotime($event_date));
                    $isScaduto = ($event_date_db < $oggi);
                    if ($isScaduto) continue;
                    $isChiuso = isset($event['chiuso']) ? $event['chiuso'] : 0;
                ?>
                  <option value="<?php echo htmlspecialchars($event['id']); ?>" <?php if ($isChiuso) echo 'disabled'; ?>>
                    <?php
                      echo htmlspecialchars(date('d-m-Y', strtotime($event_date)) . ' - ' . $event['titolo']);
                      if ($isChiuso) echo ' - OMAGGI TERMINATI';
                    ?>
                  </option>
                <?php 
                    endforeach; 
                  } // chiude if (isset($events) && is_array($events))
                ?>
              </select>
            </div>
            <div class="flex items-center">
              <input type="checkbox" id="consenso" name="consenso" required class="mr-2" />
              <label for="consenso" class="text-gray-700">Ho letto e accetto l'informativa sulla privacy.</label>
            </div>
            <div class="flex items-center">
              <input type="checkbox" id="pubblicita" name="pubblicita" class="mr-2" />
              <label for="pubblicita" class="text-gray-700">Acconsento a ricevere comunicazioni pubblicitarie.</label>
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-purple-500 to-blue-500 text-white py-2 rounded shadow-md transform transition duration-300 hover:scale-105 hover:opacity-90 focus:ring-4 focus:ring-blue-300 focus:outline-none">
              <span id="submit-text">Invia</span>
              <span id="spinner" class="hidden animate-spin ml-2">&#9696;</span>
            </button>
          </form>
        </div>
        
        <!-- Messaggio di risposta -->
        <div id="response-message" class="hidden">
          <div class="text-center">
            <div id="success-message" class="hidden fade-in bg-green-100 border-l-4 border-green-500 text-green-800 p-4 rounded-lg shadow-lg mt-4">
              <div class="mb-4">
                <div class="w-16 h-16 mx-auto mb-4 bg-green-100 rounded-full flex items-center justify-center">
                  <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                  </svg>
                </div>
                <h3 class="text-2xl font-bold text-green-600 mb-2">✅ Iscrizione completata!</h3>
                <p class="text-gray-700 mb-4" id="success-text">La tua iscrizione è stata registrata con successo.</p>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                  <p class="text-green-800 text-sm">
                    📧 Ti abbiamo inviato una email con il PDF contenente il QR code per l'accesso all'evento.
                  </p>
                </div>
              </div>
            </div>
            
            <div id="error-message" class="hidden">
              <div class="mb-4">
                <div class="w-16 h-16 mx-auto mb-4 bg-red-100 rounded-full flex items-center justify-center">
                  <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                  </svg>
                </div>
                <h3 class="text-2xl font-bold text-red-600 mb-2">❌ Errore nell'iscrizione</h3>
                <p class="text-gray-700 mb-4" id="error-text">Si è verificato un errore durante l'iscrizione.</p>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                  <p class="text-red-800 text-sm">
                    Per assistenza contatta la direzione o riprova più tardi.
                  </p>
                </div>
              </div>
            </div>
            
            <button onclick="location.reload()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition duration-300">
              🔄 Nuova Iscrizione
            </button>
          </div>
        </div>
      </div>
    </section>
  </main>
  
  <!-- Footer -->
  <footer class="bg-black text-gray-200 py-6 fade-in" style="animation-delay: 1.5s;">
    <div class="container mx-auto px-4 text-center">
      <p class="mb-2">
        Opium club | Pordenone | MOMIZ s.r.l.<br>
        Via Risiera 3, Zoppola - Pordenone<br>
        P.IVA 02721250302
      </p>
      <p>&copy; <?php echo date('Y'); ?> QrCode Sistem Developed by Benhanced. Tutti i diritti riservati.</p>
      <p>
        <a href="https://www.benhanced.it" target="_blank" rel="noopener noreferrer" class="text-indigo-400 hover:underline transition-colors">
          www.benhanced.it
        </a>
      </p>
    </div>
  </footer>
  
  <!-- Script per il menù hamburger -->
  <script>
    const menuToggle = document.getElementById('menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    menuToggle.addEventListener('click', () => {
      mobileMenu.classList.toggle('hidden');
    });
  </script>
  
  
  <script>
    document.getElementById('qrForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const form = e.target;
      const formData = new FormData(form);
      const submitButton = form.querySelector('button[type="submit"]');
      
      // Disabilita il bottone durante l'invio
      submitButton.disabled = true;
      document.getElementById('submit-text').textContent = '⏳ Invio in corso...';
      document.getElementById('spinner').classList.remove('hidden');
      
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        // Nascondi il form
        document.getElementById('form-container').style.display = 'none';
        
        // Mostra il messaggio di risposta
        const responseDiv = document.getElementById('response-message');
        responseDiv.classList.remove('hidden');
        
        if (result.success) {
          // Mostra messaggio di successo
          document.getElementById('success-message').classList.remove('hidden');
          document.getElementById('success-text').textContent = result.message;
        } else {
          // Mostra messaggio di errore
          document.getElementById('error-message').classList.remove('hidden');
          document.getElementById('error-text').textContent = result.message;
        }
        
        // Animazione di entrata
        responseDiv.style.opacity = '0';
        responseDiv.style.transform = 'translateY(20px)';
        responseDiv.style.transition = 'all 0.5s ease-out';
        
        setTimeout(() => {
          responseDiv.style.opacity = '1';
          responseDiv.style.transform = 'translateY(0)';
        }, 100);
        
      } catch (error) {
        console.error('Errore di rete:', error);
        
        // Riabilita il bottone in caso di errore di rete
        submitButton.disabled = false;
        document.getElementById('submit-text').textContent = 'Invia';
        document.getElementById('spinner').classList.add('hidden');
        
        // Mostra errore di rete
        alert('Errore di connessione. Riprova più tardi.');
      }
    });
  </script>
  
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
