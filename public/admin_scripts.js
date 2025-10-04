/* Admin Panel JavaScript - PERFORMANCE OPTIMIZED */

// ===== OPTIMIZED TIMING SYSTEM =====
const adminLoadStartTime = performance.now();
let performanceLoggingEnabled = true; // Temporarily enabled for debugging search issues

// Simplified load time tracking
function showAdminLoadTime() {
  if (!performanceLoggingEnabled) return;
  
  const loadEndTime = performance.now();
  const loadTime = Math.round(loadEndTime - adminLoadStartTime);
  
  console.log(`🎯 Admin Panel loaded in ${loadTime}ms`);
  
  if (loadTime > 2000) {
    console.warn(`⚠️ Slow load: ${loadTime}ms`);
  }
}

// Single load event listener
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(showAdminLoadTime, 50); // Reduced timeout
});

// ===== OPTIMIZED TOOLTIP SYSTEM =====
// Initialize tooltips only once and cache them
let tooltipsInitialized = false;
function initializeTooltips() {
  if (tooltipsInitialized) return;
  
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  if (tooltipTriggerList.length > 0) {
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
      new bootstrap.Tooltip(tooltipTriggerEl);
    });
    tooltipsInitialized = true;
  }
}

// ===== OPTIMIZED LOADING FUNCTION =====
function mostraCaricamento() {
  const btn = document.getElementById('rinviaTuttiBtn');
  const loader = document.getElementById('loadingIndicator');
  if (btn && loader) {
    btn.disabled = true;
    loader.style.display = 'inline';
  }
  // Reduced timeout
  setTimeout(() => {
    location.reload();
    }, 3000);
  }

// ===== OPTIMIZED GLOBAL VARIABLES =====
let originalUsersData = [];
let currentView = 'table';
let totalUsersCount = 0;
const loadedEvents = new Set();

// Optimized search cache
const searchCache = {
  initialized: false,
  elements: {
    tableRows: [],
    gridCards: []
  },
  searchableData: [],
  lastQuery: '',
  lastResults: null
};

// ===== PERFORMANCE OPTIMIZED FUNCTIONS =====

// Optimized admin data initialization
window.initializeAdminData = function(statisticheUtenti, totalUsers) {
  originalUsersData = statisticheUtenti || [];
  totalUsersCount = totalUsers || 0;
  if (performanceLoggingEnabled) {
    console.log('Data initialized:', originalUsersData.length, 'users');
  }
};

window.openBirthdaySystem = function() {
  window.open('birthday_admin.php', '_blank');
};

// ===== OPTIMIZED EVENT TOGGLE SYSTEM =====
window.toggleEventi = function(userHash) {
  const eventiRow = document.getElementById('eventi-' + userHash);
  const button = event.target.closest('button');
  const chevronIcon = document.getElementById('chevron-' + userHash);
  
  if (!eventiRow) return;
  
  const isExpanded = !eventiRow.classList.contains('d-none');
  
  if (!isExpanded) {
    // Expand
    eventiRow.classList.remove('d-none');
    
    if (chevronIcon) {
      chevronIcon.classList.remove('bi-chevron-down');
      chevronIcon.classList.add('bi-chevron-up');
    }
    if (button) {
      button.classList.remove('btn-outline-info');
      button.classList.add('btn-info');
      button.disabled = true;
    }
    
    // Load events if not cached
    if (!loadedEvents.has(userHash)) {
      loadUserEvents(userHash, button);
    } else {
      if (button) button.disabled = false;
    }
    
    // Optimized scroll
    requestAnimationFrame(() => {
      eventiRow.scrollIntoView({ 
        behavior: 'smooth', 
        block: 'nearest' 
      });
    });
  } else {
    // Collapse
    eventiRow.classList.add('d-none');
    
    if (chevronIcon) {
      chevronIcon.classList.remove('bi-chevron-up');
      chevronIcon.classList.add('bi-chevron-down');
    }
    if (button) {
      button.classList.remove('btn-info');
      button.classList.add('btn-outline-info');
      button.disabled = false;
    }
  }
};

// ===== OPTIMIZED AJAX LOADING =====
function loadUserEvents(userHash, button) {
  const userRow = button.closest('tr');
  const email = userRow ? userRow.dataset.userEmail : null;
  
  if (!email) {
    console.error('User email not found');
    if (button) button.disabled = false;
    return;
  }
  
  const ajaxStartTime = performance.now();
  
  fetch(`ajax_user_events.php?email=${encodeURIComponent(email)}`)
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return response.json();
    })
    .then(data => {
      if (performanceLoggingEnabled) {
        const duration = Math.round(performance.now() - ajaxStartTime);
        console.log(`✅ Events loaded in ${duration}ms`);
      }
      
      if (data.success) {
        const eventiRow = document.getElementById('eventi-' + userHash);
        if (eventiRow) {
          const td = eventiRow.querySelector('td');
          if (td) {
            td.innerHTML = `
              <div class="p-3">
                <h6 class="mb-3" style="color: #e0e0e0;">
                  <i class="bi bi-calendar-event me-2"></i>
                  Storico Eventi - ${data.user_info.nome} ${data.user_info.cognome}
                  <span class="badge bg-secondary ms-2">${data.stats.total_eventi} eventi</span>
                </h6>
                ${data.html}
              </div>
            `;
          }
        }
        
        loadedEvents.add(userHash);
        updateUserStatistics(data.user_info.email, data.stats);
        
        if (button) button.disabled = false;
      } else {
        throw new Error(data.error || 'Unknown error');
      }
    })
    .catch(error => {
      if (performanceLoggingEnabled) {
        const duration = Math.round(performance.now() - ajaxStartTime);
        console.error(`❌ Events failed in ${duration}ms:`, error);
      }
      
      const eventiRow = document.getElementById('eventi-' + userHash);
      if (eventiRow) {
        const td = eventiRow.querySelector('td');
        if (td) {
          td.innerHTML = `
            <div class="p-3 text-center">
              <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
              <p class="text-muted mt-2 mb-0">Error loading events</p>
              <small class="text-danger">${error.message}</small>
              <br>
              <button class="btn btn-outline-primary btn-sm mt-2" onclick="retryLoadEvents('${userHash}', this)">
                <i class="bi bi-arrow-clockwise me-1"></i>Retry
              </button>
            </div>
          `;
        }
      }
      
      if (button) button.disabled = false;
    });
}

// ===== OPTIMIZED STATISTICS UPDATE =====
function updateUserStatistics(email, stats) {
  // Batch DOM updates for better performance
  const tableRows = document.querySelectorAll('#searchResultsTable tbody tr, #allUsersTable tbody tr');
  const gridCards = document.querySelectorAll('#searchGridView .user-card, #gridView .user-card');
  
  const updateBatch = [];
  
  tableRows.forEach(row => {
    const emailCell = row.querySelector('td:nth-child(2)');
    if (emailCell && emailCell.textContent.includes(email)) {
      updateBatch.push({
        type: 'table',
        element: row,
        stats: stats
      });
    }
  });
  
  gridCards.forEach(card => {
    const emailElement = card.querySelector('.user-email');
    if (emailElement && emailElement.textContent.includes(email)) {
      updateBatch.push({
        type: 'grid',
        element: card,
        stats: stats
      });
    }
  });
  
  // Process updates in a single frame
  requestAnimationFrame(() => {
    updateBatch.forEach(update => {
      if (update.type === 'table') {
        updateTableRowStats(update.element, update.stats);
      } else {
        updateGridCardStats(update.element, update.stats);
      }
    });
  });
}

function updateTableRowStats(row, stats) {
      const iscrizioniCell = row.querySelector('td:nth-child(4)');
  const presenzeCell = row.querySelector('td:nth-child(5)');
  const tassoCell = row.querySelector('td:nth-child(6)');
  
      if (iscrizioniCell) {
        iscrizioniCell.innerHTML = `<span class="fw-bold text-primary">${stats.total_eventi}</span>`;
      }
      
      if (presenzeCell) {
        presenzeCell.innerHTML = `<span class="fw-bold text-success">${stats.eventi_validati}</span>`;
      }
      
      if (tassoCell) {
        const tassoColor = stats.tasso_presenza >= 80 ? 'text-success' : 
                          stats.tasso_presenza >= 50 ? 'text-warning' : 'text-danger';
        tassoCell.innerHTML = `<span class="fw-bold ${tassoColor}">${stats.tasso_presenza}%</span>`;
      }
      
      row.dataset.attendance = stats.tasso_presenza;
      row.dataset.registrations = stats.total_eventi;
    }

function updateGridCardStats(card, stats) {
      const iscrizioniStat = card.querySelector('.stat-iscrizioni');
      const presenzeStat = card.querySelector('.stat-presenze');
      const tassoStat = card.querySelector('.stat-tasso');
  
  if (iscrizioniStat) iscrizioniStat.textContent = stats.total_eventi;
  if (presenzeStat) presenzeStat.textContent = stats.eventi_validati;
  
      if (tassoStat) {
        tassoStat.textContent = stats.tasso_presenza + '%';
        tassoStat.className = tassoStat.className.replace(/text-(success|warning|danger)/, '');
        const tassoColor = stats.tasso_presenza >= 80 ? 'text-success' : 
                          stats.tasso_presenza >= 50 ? 'text-warning' : 'text-danger';
        tassoStat.classList.add(tassoColor);
      }
    }

// ===== OPTIMIZED SEARCH CACHE =====
function initializeSearchCache(retryCount = 0) {
  if (searchCache.initialized) return;
  
  // Verifica che gli elementi esistano prima di inizializzare
  // Cerca solo nella tabella degli eventi specifici, non nella ricerca utenti
  const tableRows = document.querySelectorAll('#allUsersTable tbody tr[data-user-email]');
  const gridCards = document.querySelectorAll('#gridView .user-card');
  
  // Se siamo nella tab "Ricerca Utenti", non inizializzare la cache locale
  // Migliora il rilevamento della tab - verifica anche se stiamo caricando quella tab
  const searchTabPane = document.querySelector('#tutti-utenti');
  const searchTabButton = document.querySelector('#tutti-utenti-tab');
  
  const isSearchTabVisible = searchTabPane && (
    // Tab già attiva
    (searchTabPane.classList.contains('show') && searchTabPane.classList.contains('active')) ||
    // Tab button attiva 
    (searchTabButton && searchTabButton.classList.contains('active')) ||
    // Non ci sono righe nella tabella eventi e abbiamo l'elemento di ricerca (probabilmente siamo in ricerca utenti)
    (tableRows.length === 0 && document.querySelector('#searchAllUsers')) ||
    // Se la pagina non ha elementi tipici degli eventi ma ha elementi di ricerca utenti
    (!document.querySelector('#allUsersTable') && document.querySelector('#searchResultsTable'))
  );
  
  if (isSearchTabVisible) {
    if (performanceLoggingEnabled) {
      console.log('ℹ️ Tab ricerca utenti attiva - cache locale disabilitata (usa ricerca AJAX)', {
        searchTabPane: !!searchTabPane,
        searchTabButton: !!searchTabButton,
        hasSearchInput: !!document.querySelector('#searchAllUsers'),
        hasSearchTable: !!document.querySelector('#searchResultsTable'),
        hasAllUsersTable: !!document.querySelector('#allUsersTable'),
        tableRowsCount: tableRows.length,
        timestamp: new Date().toISOString()
      });
    }
    searchCache.initialized = true;
    searchCache.elements = { tableRows: [], gridCards: [] };
    searchCache.searchableData = [];
    return;
  }
  
  if (tableRows.length === 0) {
    // Limita i tentativi per evitare loop infiniti
    if (retryCount >= 5) {
      if (performanceLoggingEnabled) {
        console.warn('❌ Impossibile inizializzare cache di ricerca dopo 5 tentativi - probabilmente non ci sono utenti per questo evento');
      }
      // Marca come inizializzata anche se vuota per evitare loop infiniti
      searchCache.initialized = true;
      searchCache.elements = { tableRows: [], gridCards: [] };
      searchCache.searchableData = [];
      return;
    }
    
    if (performanceLoggingEnabled && retryCount === 0) {
      console.warn(`⚠️ Nessuna riga utente trovata per inizializzare la cache di ricerca (tentativo ${retryCount + 1}/5)`);
    }
    
    // Ritenta dopo un breve delay
    setTimeout(() => {
      initializeSearchCache(retryCount + 1);
    }, 200 + (retryCount * 100)); // Delay crescente
    return;
  }
  
  searchCache.elements = {
    tableRows: Array.from(tableRows),
    gridCards: Array.from(gridCards)
  };
  
  // Pre-compute search data with optimized text extraction
  searchCache.searchableData = searchCache.elements.tableRows.map(row => {
    const cells = row.querySelectorAll('td');
    const textContent = Array.from(cells).map(cell => cell.textContent.toLowerCase()).join(' ');
    
    return {
      element: row,
      text: textContent,
      email: row.dataset.userEmail?.toLowerCase() || ''
    };
  });
  
  searchCache.initialized = true;
  
  if (performanceLoggingEnabled) {
    console.log('✅ Cache ricerca inizializzata con successo:', {
      righeTabella: searchCache.elements.tableRows.length,
      carteGriglia: searchCache.elements.gridCards.length,
      datiRicercabili: searchCache.searchableData.length
    });
  }
}

// ===== OPTIMIZED SEARCH WITH DEBOUNCE =====
let searchTimeout;
let lastSearchTime = 0;
const SEARCH_DEBOUNCE_DELAY = 300; // Increased for better performance

window.filterUsers = function() {
  const now = Date.now();
  
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    if (now - lastSearchTime < 100) return; // Prevent too frequent searches
    lastSearchTime = now;
    performSearch();
  }, SEARCH_DEBOUNCE_DELAY);
};

function performSearch() {
  const searchInput = document.getElementById('searchAllUsers');
  if (!searchInput) {
    if (performanceLoggingEnabled) {
      console.warn('⚠️ Input di ricerca non trovato');
    }
    return;
  }
  
  const searchTerm = searchInput.value.toLowerCase().trim();
  
  // Se siamo nella tab "Ricerca Utenti", usa il sistema AJAX invece della cache locale
  const searchTabPane = document.querySelector('#tutti-utenti');
  const searchTabButton = document.querySelector('#tutti-utenti-tab');
  
  const isSearchTabVisible = searchTabPane && (
    // Tab già attiva
    (searchTabPane.classList.contains('show') && searchTabPane.classList.contains('active')) ||
    // Tab button attiva 
    (searchTabButton && searchTabButton.classList.contains('active')) ||
    // Elemento di ricerca presente (probabilmente siamo in ricerca utenti)
    document.querySelector('#searchAllUsers') ||
    // Se la pagina non ha elementi tipici degli eventi ma ha elementi di ricerca utenti
    (!document.querySelector('#allUsersTable') && document.querySelector('#searchResultsTable'))
  );
  
  if (isSearchTabVisible) {
    if (performanceLoggingEnabled) {
      console.log('ℹ️ Ricerca su tab utenti - delego al sistema AJAX');
    }
    return; // Il sistema AJAX gestisce la ricerca in questo tab
  }
  
  // Cache optimization: return cached results if same query
  if (searchCache.lastQuery === searchTerm && searchCache.lastResults) {
    applySearchResults(searchCache.lastResults);
    return;
  }
  
  const attendanceFilter = document.getElementById('filterByAttendance');
  const registrationsFilter = document.getElementById('filterByRegistrations');
  const statusFilter = document.getElementById('filterByStatus');
  
  const attendanceValue = attendanceFilter ? attendanceFilter.value : '';
  const registrationsValue = registrationsFilter ? registrationsFilter.value : '';
  const statusValue = statusFilter ? statusFilter.value : '';
  
  // Assicurati che la cache sia inizializzata
  if (!searchCache.initialized) {
    initializeSearchCache();
  }
  
  // Verifica che la cache sia stata inizializzata correttamente
  if (!searchCache.initialized) {
    if (performanceLoggingEnabled) {
      console.error('❌ Cache di ricerca non inizializzata');
    }
    showToast('Cache di ricerca non inizializzata. Riprova tra un momento.', 'warning');
    return;
  }
  
  // Se la cache è inizializzata ma vuota, probabilmente non ci sono utenti per questo evento
  if (!searchCache.searchableData || searchCache.searchableData.length === 0) {
    if (performanceLoggingEnabled) {
      console.log('ℹ️ Cache di ricerca vuota - nessun utente per questo evento');
    }
    updateUsersCount(0);
    showNoResultsMessage(true);
    return;
  }
  
  const results = [];
  let visibleCount = 0;
  
  // Optimized search loop
  searchCache.searchableData.forEach((item, index) => {
    const tableRow = item.element;
    const gridCard = searchCache.elements.gridCards[index];
    
    let showElement = true;
    
    // Text search optimization
    if (searchTerm !== '') {
      const searchWords = searchTerm.split(' ').filter(word => word.length > 0);
      const matches = searchWords.every(word => 
        item.text.includes(word) || item.email.includes(word)
      );
      if (!matches) showElement = false;
    }
    
    // Apply filters only if text search passes
    if (showElement) {
      showElement = applyFilters(tableRow, attendanceValue, registrationsValue, statusValue);
    }
    
    results.push({
      tableRow,
      gridCard,
      visible: showElement
    });
    
    if (showElement) visibleCount++;
  });
  
  // Cache results
  searchCache.lastQuery = searchTerm;
  searchCache.lastResults = results;
  
  applySearchResults(results);
  updateUsersCount(visibleCount);
  showNoResultsMessage(visibleCount === 0);
}

function applyFilters(tableRow, attendanceValue, registrationsValue, statusValue) {
  if (attendanceValue !== '') {
      const attendance = parseFloat(tableRow.dataset.attendance || '0');
      switch (attendanceValue) {
      case 'excellent': if (attendance < 80) return false; break;
      case 'good': if (attendance < 60 || attendance >= 80) return false; break;
      case 'fair': if (attendance < 40 || attendance >= 60) return false; break;
      case 'poor': if (attendance < 1 || attendance >= 40) return false; break;
      case 'zero': if (attendance > 0) return false; break;
    }
  }
  
  if (registrationsValue !== '') {
      const registrations = parseInt(tableRow.dataset.registrations || '0');
      switch (registrationsValue) {
      case '1': if (registrations !== 1) return false; break;
      case '2-5': if (registrations < 2 || registrations > 5) return false; break;
      case '6-10': if (registrations < 6 || registrations > 10) return false; break;
      case '10+': if (registrations <= 10) return false; break;
    }
  }
  
  if (statusValue !== '') {
      const isBlocked = tableRow.dataset.isBlocked === 'true';
      switch (statusValue) {
      case 'active': if (isBlocked) return false; break;
      case 'blocked': if (!isBlocked) return false; break;
    }
  }
  
  return true;
}

function applySearchResults(results) {
  // Batch DOM updates
  requestAnimationFrame(() => {
    results.forEach(result => {
      const display = result.visible ? '' : 'none';
      result.tableRow.style.display = display;
      if (result.gridCard) result.gridCard.style.display = display;
    });
  });
}

// ===== OPTIMIZED UTILITY FUNCTIONS =====
function generateUserHash(email) {
  return btoa(email).replace(/[^a-zA-Z0-9]/g, '').substring(0, 10);
}

function updateUsersCount(count) {
  const counter = document.getElementById('usersCount');
  if (counter && counter.textContent !== count.toString()) {
    counter.textContent = count;
  }
}

function showNoResultsMessage(show) {
  let noResultsRow = document.getElementById('noResultsRow');
  let noResultsGrid = document.getElementById('noResultsGrid');
  
  if (show) {
    if (!noResultsRow) {
      const tbody = document.querySelector('#allUsersTable tbody');
      if (tbody) {
        noResultsRow = document.createElement('tr');
        noResultsRow.id = 'noResultsRow';
        noResultsRow.innerHTML = `
          <td colspan="8" class="text-center py-5">
            <div class="d-flex flex-column align-items-center">
              <i class="bi bi-search text-muted mb-3" style="font-size: 3rem;"></i>
              <h5 class="text-muted mb-2">No users found</h5>
              <p class="text-muted mb-0">Try modifying your search terms</p>
              <button class="btn btn-outline-primary btn-sm mt-3" onclick="document.getElementById('searchAllUsers').value=''; filterUsers();">
                <i class="bi bi-arrow-clockwise me-1"></i>Show all
              </button>
            </div>
          </td>
        `;
        tbody.appendChild(noResultsRow);
      }
  }
  
    if (!noResultsGrid) {
      const gridContainer = document.getElementById('gridView');
      if (gridContainer) {
        noResultsGrid = document.createElement('div');
        noResultsGrid.id = 'noResultsGrid';
        noResultsGrid.className = 'col-12 text-center py-5';
        noResultsGrid.innerHTML = `
          <div class="d-flex flex-column align-items-center">
            <i class="bi bi-search text-muted mb-3" style="font-size: 3rem;"></i>
            <h5 class="text-muted mb-2">No users found</h5>
            <p class="text-muted mb-0">Try modifying your search terms</p>
            <button class="btn btn-outline-primary btn-sm mt-3" onclick="document.getElementById('searchAllUsers').value=''; filterUsers();">
              <i class="bi bi-arrow-clockwise me-1"></i>Show all
            </button>
          </div>
        `;
        gridContainer.appendChild(noResultsGrid);
      }
    }
    
    if (noResultsRow) noResultsRow.style.display = '';
    if (noResultsGrid) noResultsGrid.style.display = '';
  } else {
    if (noResultsRow) noResultsRow.style.display = 'none';
    if (noResultsGrid) noResultsGrid.style.display = 'none';
  }
}

// ===== OPTIMIZED TOAST SYSTEM =====
let toastContainer = null;

function initializeToastContainer() {
  if (toastContainer) return;
  
     toastContainer = document.createElement('div');
   toastContainer.id = 'toast-container';
   toastContainer.style.cssText = `
     position: fixed;
     top: 20px;
     right: 20px;
     z-index: 9999;
     max-width: 400px;
   `;
   document.body.appendChild(toastContainer);
}

function showToast(message, type = 'info') {
  initializeToastContainer();
  
  // Remove existing toasts
  const existingToasts = toastContainer.querySelectorAll('.custom-toast');
  existingToasts.forEach(toast => toast.remove());
  
  const toast = document.createElement('div');
  toast.className = `custom-toast ${type}`;
  toast.innerHTML = `
    <div class="d-flex align-items-center">
      <i class="bi ${getToastIcon(type)} me-2"></i>
      <span class="flex-grow-1">${message}</span>
      <button type="button" class="btn-close btn-close-white ms-2" onclick="this.parentElement.parentElement.remove()"></button>
    </div>
  `;
  
  toastContainer.appendChild(toast);
  
  // Animate in
  requestAnimationFrame(() => {
    toast.classList.add('show');
  });
  
  // Auto remove
  setTimeout(() => {
    if (toast.parentElement) {
      toast.classList.remove('show');
      setTimeout(() => {
        if (toast.parentElement) {
          toast.remove();
        }
      }, 300);
    }
  }, 4000);
}

function getToastIcon(type) {
  switch(type) {
    case 'success': return 'bi-check-circle-fill';
    case 'danger': return 'bi-exclamation-triangle-fill';
    case 'warning': return 'bi-exclamation-triangle-fill';
    case 'info': 
    default: return 'bi-info-circle-fill';
  }
}

// ===== OPTIMIZED VIEW TOGGLE =====
window.toggleView = function(viewType) {
  const tableView = document.getElementById('tableView');
  const gridView = document.getElementById('gridView');
  const viewTable = document.getElementById('viewTable');
  const viewGrid = document.getElementById('viewGrid');
  
  if (viewType === 'grid') {
    tableView?.classList.add('d-none');
    gridView?.classList.remove('d-none');
    viewTable?.classList.remove('active');
    viewGrid?.classList.add('active');
    currentView = 'grid';
    } else {
    tableView?.classList.remove('d-none');
    gridView?.classList.add('d-none');
    viewTable?.classList.add('active');
    viewGrid?.classList.remove('active');
    currentView = 'table';
  }
};

// ===== OPTIMIZED SORTING =====
window.sortUsers = function(sortBy) {
  const tableBody = document.querySelector('#allUsersTable tbody');
  const gridContainer = document.getElementById('gridView');
  
  if (!tableBody || !gridContainer) return;
  
  const tableRows = Array.from(tableBody.querySelectorAll('tr')).filter(row => 
    !row.id || !row.id.startsWith('eventi-')
  );
  const gridCards = Array.from(gridContainer.querySelectorAll('.user-card'));
  
  const sortFunction = getSortFunction(sortBy);
  
  // Sort arrays
  const sortedTableRows = [...tableRows].sort(sortFunction);
  const sortedGridCards = [...gridCards].sort(sortFunction);
  
  // Collect detail rows
  const detailRowsMap = new Map();
  tableRows.forEach(row => {
    const userEmail = row.dataset.userEmail;
    if (userEmail) {
      const userHash = generateUserHash(userEmail);
      const detailRow = document.getElementById('eventi-' + userHash);
      if (detailRow) {
        detailRowsMap.set(userEmail, detailRow.cloneNode(true));
      }
    }
  });
  
  // Batch DOM updates
  requestAnimationFrame(() => {
    // Update table
    tableBody.innerHTML = '';
    sortedTableRows.forEach(row => {
      tableBody.appendChild(row);
      const userEmail = row.dataset.userEmail;
      if (userEmail && detailRowsMap.has(userEmail)) {
        tableBody.appendChild(detailRowsMap.get(userEmail));
      }
    });
    
    // Update grid
    gridContainer.innerHTML = '';
    sortedGridCards.forEach(card => {
      gridContainer.appendChild(card);
    });
  });
  
  updateSortIndicator(sortBy);
};

function getSortFunction(sortBy) {
  return (a, b) => {
    let valueA, valueB;
    
    switch (sortBy) {
      case 'registrations_desc':
        valueA = parseInt(a.dataset.registrations || '0');
        valueB = parseInt(b.dataset.registrations || '0');
        return valueB - valueA;
        
      case 'registrations_asc':
        valueA = parseInt(a.dataset.registrations || '0');
        valueB = parseInt(b.dataset.registrations || '0');
        return valueA - valueB;
        
      case 'attendance_desc':
        valueA = parseFloat(a.dataset.attendance || '0');
        valueB = parseFloat(b.dataset.attendance || '0');
        return valueB - valueA;
        
      case 'attendance_asc':
        valueA = parseFloat(a.dataset.attendance || '0');
        valueB = parseFloat(b.dataset.attendance || '0');
        return valueA - valueB;
        
      case 'name_asc':
        valueA = getUserName(a).toLowerCase();
        valueB = getUserName(b).toLowerCase();
        return valueA.localeCompare(valueB, 'it', { sensitivity: 'base' });
        
      case 'name_desc':
        valueA = getUserName(a).toLowerCase();
        valueB = getUserName(b).toLowerCase();
        return valueB.localeCompare(valueA, 'it', { sensitivity: 'base' });
        
      default:
        return 0;
    }
  };
}

function getUserName(element) {
  if (currentView === 'grid') {
    return element.querySelector('h6.card-title')?.textContent.trim() || '';
  } else {
    const cell = element.querySelector('td:first-child .fw-semibold');
    return cell ? cell.textContent.trim() : '';
  }
}

function updateSortIndicator(sortBy) {
  const sortSelect = document.getElementById('sortUsers');
  if (sortSelect && sortSelect.value !== sortBy) {
    sortSelect.value = sortBy;
  }
}

// ===== OPTIMIZED EVENT LISTENERS =====
// Single DOMContentLoaded handler with optimized initialization
document.addEventListener('DOMContentLoaded', function() {
  // Initialize tooltips
  initializeTooltips();
  
  // Initialize toast container
  initializeToastContainer();
  
  // Initialize search cache
  initializeSearchCache();
  
  // Setup event listeners with passive listeners where possible
  setupEventListeners();
  
  // Initialize table highlights
  const highlightRow = document.querySelector('.table-warning');
  if (highlightRow) {
    highlightRow.scrollIntoView({behavior: 'smooth', block: 'center'});
    setTimeout(() => {
      highlightRow.classList.remove('table-warning');
    }, 2000);
  }
  
  if (performanceLoggingEnabled) {
    console.log('🚀 Admin panel fully initialized');
  }
});

// ===== FUNZIONE ELIMINAZIONE UTENTE (robusta) =====
window.deleteUser = function(userId, clickedEl = null) {
  if (!userId) {
    showToast('ID utente non valido', 'warning');
    return;
  }

  if (!confirm("Sei sicuro di voler eliminare questo utente?")) return;

  fetch(ADMIN_ENDPOINT, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      'Accept': 'application/json'
    },
    body: `action=delete_user&id=${encodeURIComponent(userId)}`
  })
  .then(async (res) => {
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      // Probabile HTML (login scaduto o errore PHP)
      if (text.startsWith('<')) {
        showToast('Sessione scaduta? Ricarica la pagina e riprova.', 'warning');
      } else {
        showToast('Risposta non valida dal server', 'danger');
      }
      throw new Error('Invalid JSON response: ' + text.slice(0, 200));
    }
    return data;
  })
  .then((data) => {
    if (data.success) {
      showToast(data.message || 'Utente eliminato', 'success');

      // 1) Rimuovi riga tabella collegata al bottone cliccato (fallback)
      let row = clickedEl ? clickedEl.closest('tr') : null;

      // 2) Se non trovata, cerca per data-user-id
      if (!row) {
        row = document.querySelector(`#allUsersTable tr[data-user-id="${CSS.escape(String(userId))}"]`);
      }

      // 3) Rimuovi eventuale riga dettagli eventi associata (se conosciamo l'email)
      if (row) {
        const email = row.dataset.userEmail;
        row.remove();
        if (email) {
          // Calcola lo userHash come in generateUserHash()
          const userHash = btoa(email).replace(/[^a-zA-Z0-9]/g, '').substring(0, 10);
          const detailRow = document.getElementById('eventi-' + userHash);
          if (detailRow) detailRow.remove();
        }
      }

      // 4) Rimuovi eventuale card nella vista griglia
      let card = clickedEl ? clickedEl.closest('.user-card') : null;
      if (!card) {
        card = document.querySelector(`#gridView .user-card[data-user-id="${CSS.escape(String(userId))}"]`);
      }
      if (card) card.remove();

      // Aggiorna contatore utenti visibili (se presente)
      const visibleRows = document.querySelectorAll('#allUsersTable tbody tr:not([id^="eventi-"])');
      updateUsersCount(visibleRows ? visibleRows.length : 0);

    } else {
      showToast(data.error || 'Errore durante l\'eliminazione', 'danger');
    }
  })
  .catch((err) => {
    console.error('Errore eliminazione:', err);
    showToast('Errore di connessione al server', 'danger');
  });
};

// ===== EVENT DELEGATION per bottoni "Elimina" =====
document.addEventListener('click', function(e) {
  // Cattura vari tipi di trigger possibili
  let btn = e.target.closest('[data-action="delete-user"], .btn-delete-user, button[data-user-id], button[data-id]');
  if (!btn) {
    // Fallback: icona cestino dentro un button
    const icon = e.target.closest('.bi-trash, .bi-trash-fill');
    if (icon) btn = icon.closest('button');
  }
  if (!btn) return;

  // Ricava l'ID utente da più possibili attributi/dataset
  const userId = btn.dataset.userId || btn.dataset.id || btn.getAttribute('data-user-id') || btn.getAttribute('data-id');
  const idFromRow = btn.closest('tr')?.dataset?.userId;

  const finalId = userId || idFromRow;
  if (!finalId) {
    showToast('ID utente non trovato nel DOM', 'warning');
    return;
  }

  e.preventDefault();
  window.deleteUser(finalId, btn);
}, { passive: false });

function setupEventListeners() {
  // Search input - gestito dal sistema AJAX nella tab utenti
  // Non configurare event listeners qui per evitare conflitti
  
  const clearButton = document.getElementById('clearSearch');
  if (clearButton) {
    clearButton.addEventListener('click', function() {
      const searchInput = document.getElementById('searchAllUsers');
      if (searchInput) {
        searchInput.value = '';
        showSearchState('initial');
      }
    });
  }
  
  // Filter dropdowns - gestiti dal sistema AJAX specifico per tab
  // Rimossi per evitare conflitti con il sistema AJAX
  
  // Sort dropdown
  const sortSelect = document.getElementById('sortUsers');
  if (sortSelect) {
    sortSelect.addEventListener('change', function() {
      sortUsers(this.value);
    }, { passive: true });
  }
  
  // View toggle buttons
  const viewButtons = [
    { id: 'viewTable', view: 'table' },
    { id: 'viewGrid', view: 'grid' }
  ];

  viewButtons.forEach(({ id, view }) => {
    const btn = document.getElementById(id);
    if (btn) {
      btn.addEventListener('click', () => toggleView(view));
    }
  });
}

// ===== SUPPORTO FUNZIONI IMPOSTAZIONI =====
const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
const ADMIN_ENDPOINT = (() => {
  if (/\/admin(?:\.php)?$/.test(currentPath)) {
    return currentPath;
  }
  const base = currentPath.endsWith('/') ? currentPath : currentPath.replace(/[^/]*$/, '');
  return (base.endsWith('/') ? base : `${base}/`) + 'admin.php';
})();

const ADMIN_BASE_PATH = (() => {
  const withoutAdmin = ADMIN_ENDPOINT.replace(/admin(?:\.php)?$/, '');
  return withoutAdmin.endsWith('/') ? withoutAdmin : `${withoutAdmin}/`;
})();

function buildRelativeUrl(path) {
  if (/^https?:/i.test(path)) return path;
  if (path.startsWith('/')) return path;
  return (ADMIN_BASE_PATH || '/') + path.replace(/^\//, '');
}

const defaultEmailTexts = {
  subject: 'Iscrizione Confermata - {evento}',
  header_title: 'Iscrizione Confermata',
  header_subtitle: 'Mr.Charlie Lignano Sabbiadoro',
  greeting_message: 'La tua registrazione è stata completata con successo. Tutti i dettagli sono confermati.',
  qr_title: 'Codice QR di Accesso',
  qr_description: 'Il QR Code ti servirà per l\'accesso all\'evento',
  qr_note: 'Conserva il PDF allegato e presentalo all\'ingresso',
  instructions_title: 'Informazioni Importanti',
  instruction_1: 'Porta con te il PDF allegato (digitale o stampato)',
  instruction_2: 'Arriva in tempo per l\'ingresso',
  instruction_3: 'Il QR Code è personale e non trasferibile',
  instruction_4: 'Per modifiche o cancellazioni, contattaci immediatamente',
  status_message: 'Tutto pronto per l\'evento',
  footer_title: 'Mr.Charlie Lignano Sabbiadoro',
  footer_subtitle: 'Il tuo locale di fiducia per eventi indimenticabili',
  footer_email: 'info@mrcharlie.it',
  footer_location: 'Lignano Sabbiadoro, Italia',
  footer_disclaimer: 'Questa email è stata generata automaticamente. Per assistenza, rispondi a questa email.'
};

const emailTextFields = Object.keys(defaultEmailTexts);
let cachedEmailTexts = null;

function showAlertSafe(type, message) {
  if (typeof showAlert === 'function') {
    showAlert(type, message);
  } else {
    alert(`[${type}] ${message}`);
  }
}

async function postAdminAction(action, params = {}) {
  const formData = new URLSearchParams();
  formData.set('action', action);

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null) {
      return;
    }

    if (Array.isArray(value)) {
      value.forEach(item => formData.append(`${key}[]`, item));
      return;
    }

    if (typeof value === 'object' && !(value instanceof Date)) {
      Object.entries(value).forEach(([subKey, subValue]) => {
        if (subValue === undefined || subValue === null) {
          return;
        }
        formData.append(`${key}[${subKey}]`, subValue);
      });
      return;
    }

    formData.append(key, value);
  });

  const response = await fetch(ADMIN_ENDPOINT, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      'Accept': 'application/json'
    },
    body: formData.toString()
  });

  const raw = (await response.text()).trim();
  if (!response.ok) {
    throw new Error(raw || `Errore richiesta (${response.status})`);
  }
  try {
    return JSON.parse(raw || '{}');
  } catch (error) {
    throw new Error(raw || 'Risposta non valida dal server');
  }
}

function updateBadge(badgeId, state, label) {
  const badge = document.getElementById(badgeId);
  if (!badge) return;

  badge.textContent = label;
  badge.classList.remove('bg-secondary', 'bg-success', 'bg-danger', 'bg-warning');

  if (state === 'loading') {
    badge.classList.add('bg-warning');
  } else if (state === true) {
    badge.classList.add('bg-success');
  } else if (state === false) {
    badge.classList.add('bg-danger');
  } else {
    badge.classList.add('bg-secondary');
  }
}

function appendTestLog(message, type = 'info') {
  const testContent = document.getElementById('testContent');
  if (!testContent) return;

  const prefix = type === 'success' ? '✅' : type === 'error' ? '❌' : '•';
  testContent.textContent += `${prefix} ${message}\n`;
  testContent.scrollTop = testContent.scrollHeight;
}

function updateTestProgress(percent, text) {
  const progressBar = document.getElementById('progressBar');
  const progressText = document.getElementById('progressText');
  const testProgress = document.getElementById('testProgress');

  if (testProgress && progressBar && progressText) {
    testProgress.style.display = 'block';
    progressBar.style.width = `${Math.max(0, Math.min(100, percent))}%`;
    progressText.textContent = text;
  }
}

function populateEmailForm(texts) {
  const form = document.getElementById('emailTextsForm');
  if (!form) return;

  emailTextFields.forEach((field) => {
    const input = form.querySelector(`[name="${field}"]`);
    if (!input) return;
    input.value = texts[field] ?? '';
  });
}

function getEmailFormValues() {
  const form = document.getElementById('emailTextsForm');
  const values = {};
  if (!form) return values;

  emailTextFields.forEach((field) => {
    const input = form.querySelector(`[name="${field}"]`);
    values[field] = input ? input.value.trim() : '';
  });

  return values;
}

function escapeHtml(text) {
  return String(text ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function buildEmailPreviewHtml(texts) {
  const sampleVars = {
    '{nome}': 'Giulia',
    '{cognome}': 'Rossi',
    '{evento}': 'Evento Demo',
    '{data}': new Date().toLocaleDateString('it-IT')
  };

  const replaceVars = (value) => {
    let output = value ?? '';
    Object.entries(sampleVars).forEach(([key, sampleValue]) => {
      output = output.split(key).join(sampleValue);
    });
    return output;
  };

  return `<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Anteprima Email</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }
    .wrapper { max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18); }
    .header { background: linear-gradient(135deg, #4b8be4 0%, #082f6b 100%); color: #fff; padding: 32px; }
    .header h1 { margin: 0 0 6px; font-size: 24px; }
    .section { padding: 24px 32px; border-bottom: 1px solid rgba(15, 23, 42, 0.08); }
    .section:last-child { border-bottom: none; }
    .qr-box { background: rgba(59, 130, 246, 0.08); border-radius: 10px; padding: 18px; margin-top: 12px; }
    .instructions ul { margin: 12px 0 0; padding-left: 20px; }
    .footer { background: #0f172a; color: #e2e8f0; padding: 24px 32px; font-size: 13px; }
    .footer strong { display: inline-block; margin-bottom: 6px; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1>${escapeHtml(replaceVars(texts.header_title))}</h1>
      <p>${escapeHtml(replaceVars(texts.header_subtitle))}</p>
    </div>
    <div class="section">
      <p>${escapeHtml(replaceVars(texts.greeting_message))}</p>
      <p style="margin-top: 14px; color: #0f172a; font-weight: 600;">${escapeHtml(replaceVars(texts.status_message))}</p>
    </div>
    <div class="section">
      <h3 style="margin: 0 0 10px;">${escapeHtml(replaceVars(texts.qr_title))}</h3>
      <div class="qr-box">
        <p style="margin: 0 0 6px;">${escapeHtml(replaceVars(texts.qr_description))}</p>
        <small style="display: block; color: #1f2937;">${escapeHtml(replaceVars(texts.qr_note))}</small>
      </div>
    </div>
    <div class="section instructions">
      <h3 style="margin-top: 0;">${escapeHtml(replaceVars(texts.instructions_title))}</h3>
      <ul>
        <li>${escapeHtml(replaceVars(texts.instruction_1))}</li>
        <li>${escapeHtml(replaceVars(texts.instruction_2))}</li>
        <li>${escapeHtml(replaceVars(texts.instruction_3))}</li>
        <li>${escapeHtml(replaceVars(texts.instruction_4))}</li>
      </ul>
    </div>
    <div class="footer">
      <strong>${escapeHtml(replaceVars(texts.footer_title))}</strong>
      <div>${escapeHtml(replaceVars(texts.footer_subtitle))}</div>
      <div>${escapeHtml(replaceVars(texts.footer_email))}</div>
      <div>${escapeHtml(replaceVars(texts.footer_location))}</div>
      <p style="margin-top: 14px; opacity: 0.75;">${escapeHtml(replaceVars(texts.footer_disclaimer))}</p>
    </div>
  </div>
</body>
</html>`;
}

function toggleElementDisplay(id, visible) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.display = visible ? 'block' : 'none';
}

// ===== LOG SYSTEM =====
const logLabels = {
  rinvii: 'Invii Email',
  errori: 'Errori',
  accessi: 'Accessi'
};

window.viewLog = async function(logType) {
  const viewer = document.getElementById('logViewer');
  const logContent = document.getElementById('logContent');
  const logTitle = document.getElementById('logViewerTitle');

  if (viewer) viewer.style.display = 'block';
  if (logContent) logContent.textContent = 'Caricamento log in corso...';
  if (logTitle) {
    const label = logLabels[logType] || logType;
    logTitle.innerHTML = `<i class="bi bi-file-text me-2"></i>Contenuto Log - ${escapeHtml(label)}`;
  }

  try {
    const data = await postAdminAction('view_log', { log_type: logType });
    if (data.success) {
      if (logContent) {
        logContent.textContent = data.content || '(Log vuoto)';
      }
    } else {
      throw new Error(data.error || 'Impossibile leggere il log');
    }
  } catch (error) {
    if (logContent) {
      logContent.textContent = `Errore durante il caricamento del log: ${error.message}`;
    }
    showAlertSafe('danger', error.message);
  }
};

window.clearLog = async function(logType) {
  const label = logLabels[logType] || logType;
  if (!confirm(`Sei sicuro di voler pulire il log "${label}"?`)) return;

  try {
    const data = await postAdminAction('clear_log', { log_type: logType });
    if (data.success) {
      showAlertSafe('success', data.message || 'Log pulito con successo');
      const logContent = document.getElementById('logContent');
      if (logContent) {
        logContent.textContent = '# Log pulito - Nessun contenuto da mostrare';
      }
    } else {
      throw new Error(data.error || 'Impossibile pulire il log');
    }
  } catch (error) {
    showAlertSafe('danger', error.message);
  }
};

window.closeLogViewer = function() {
  toggleElementDisplay('logViewer', false);
};

// ===== TEST SISTEMA =====
window.testSistema = async function() {
  const testResults = document.getElementById('testResults');
  const testContent = document.getElementById('testContent');
  const testProgress = document.getElementById('testProgress');
  if (!testResults || !testContent) return;

  testResults.style.display = 'block';
  testContent.textContent = '';
  if (testProgress) testProgress.style.display = 'none';

  const testEmailInput = document.getElementById('testEmail');
  const testEmail = testEmailInput ? testEmailInput.value.trim() : '';
  const eventInput = document.getElementById('testEventId');
  const eventId = eventInput && eventInput.value ? parseInt(eventInput.value, 10) : NaN;

  if (!testEmail) {
    appendTestLog('Email di test non specificata', 'error');
    showAlertSafe('warning', 'Inserisci un indirizzo email per il test.');
    return;
  }

  if (!eventId) {
    appendTestLog('Nessun evento aperto disponibile per eseguire il test completo.', 'error');
    showAlertSafe('warning', 'Nessun evento aperto trovato. Crea o riapri un evento prima di lanciare il test.');
    return;
  }

  updateTestProgress(10, 'Preparazione test...');

  let testUserCreated = false;
  const cleanup = async () => {
    try {
      await postAdminAction('cleanup_test', { email: testEmail });
    } catch (error) {
      appendTestLog(`Errore durante la pulizia dati di test: ${error.message}`, 'error');
    }
  };

  try {
    appendTestLog('Pulizia dati di test precedenti...', 'info');
    await cleanup();
    appendTestLog('Pulizia precedente completata.', 'success');
    updateTestProgress(20, 'Dati di test ripuliti');

    appendTestLog('Verifica connessione database...', 'info');
    const dbResult = await postAdminAction('test_database');
    if (!dbResult.success) {
      throw new Error(dbResult.error || 'Database non raggiungibile');
    }
    appendTestLog(`Database OK (versione ${dbResult.version || 'n/d'})`, 'success');
    updateBadge('dbStatus', true, 'Connesso');
    updateTestProgress(35, 'Database verificato');

    appendTestLog('Verifica configurazione SMTP...', 'info');
    const smtpResult = await postAdminAction('test_smtp');
    if (!smtpResult.success) {
      throw new Error(smtpResult.error || 'SMTP non configurato');
    }
    appendTestLog(`SMTP OK (${smtpResult.host}:${smtpResult.port})`, 'success');
    updateBadge('smtpStatus', true, 'Configurato');
    updateTestProgress(55, 'SMTP verificato');

    appendTestLog('Invio registrazione di test al form...', 'info');
    updateBadge('formStatus', 'loading', 'Invio test...');

    const testPayload = new URLSearchParams({
      nome: 'Test',
      cognome: 'Sistema',
      email: testEmail,
      telefono: '+393331234567',
      'data-nascita': '1990-01-01',
      evento: eventId,
      test_mode: 'true'
    }).toString();

    const saveFormEndpoints = [
      buildRelativeUrl('save_form.php'),
      buildRelativeUrl('save-form'),
      '/save-form'
    ];

    let formResponse;
    let formRaw = '';
    let lastError = null;

    for (const endpoint of saveFormEndpoints) {
      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept': 'application/json'
          },
          body: testPayload
        });

        const text = (await response.text()).trim();
        if (!response.ok) {
          lastError = new Error(text || `Errore ${response.status}`);
          continue;
        }

        formResponse = response;
        formRaw = text;
        break;
      } catch (error) {
        lastError = error;
      }
    }

    if (!formResponse) {
      throw lastError || new Error('Endpoint save-form non raggiungibile');
    }

    let formData;
    try {
      formData = JSON.parse(formRaw);
    } catch (error) {
      throw new Error(formRaw || 'Risposta non valida dal form');
    }

    if (!formResponse.ok || !formData.success) {
      throw new Error((formData && formData.message) || 'Errore durante il test del form');
    }

    testUserCreated = true;
    appendTestLog('Registrazione di test completata con successo. Email inviata.', 'success');
    updateBadge('formStatus', true, 'Operativo');
    updateTestProgress(75, 'Form verificato');

    appendTestLog('Pulizia dati di test...', 'info');
    await cleanup();
    appendTestLog('Dati di test eliminati.', 'success');
    updateTestProgress(90, 'Pulizia completata');

    appendTestLog('Test completato! Controlla la casella email specificata per verificare la ricezione.', 'success');
    updateTestProgress(100, 'Test completato con successo');

    showAlertSafe('success', 'Test sistema completato. Controlla l\'email di test per confermare l\'invio.');
  } catch (error) {
    appendTestLog(`Errore durante il test: ${error.message}`, 'error');
    updateTestProgress(100, 'Test interrotto per errore');
    updateBadge('formStatus', false, 'Errore');
    showAlertSafe('danger', error.message);

    if (testUserCreated) {
      await cleanup();
    }
  }
};

window.closeTestResults = function() {
  toggleElementDisplay('testResults', false);
};

// ===== VERIFICHE SINGOLE =====
window.verificaDatabase = async function() {
  updateBadge('dbStatus', 'loading', 'Verifica in corso...');
  try {
    const data = await postAdminAction('test_database');
    if (data.success) {
      updateBadge('dbStatus', true, 'Connesso');
      showAlertSafe('success', `Database connesso (versione ${data.version || 'n/d'})`);
    } else {
      throw new Error(data.error || 'Database non raggiungibile');
    }
  } catch (error) {
    updateBadge('dbStatus', false, 'Errore');
    showAlertSafe('danger', error.message);
  }
};

window.verificaEmail = async function() {
  updateBadge('smtpStatus', 'loading', 'Verifica in corso...');
  try {
    const data = await postAdminAction('test_smtp');
    if (data.success) {
      updateBadge('smtpStatus', true, 'Configurato');
      showAlertSafe('success', `SMTP operativo (${data.host}:${data.port})`);
    } else {
      throw new Error(data.error || 'SMTP non configurato');
    }
  } catch (error) {
    updateBadge('smtpStatus', false, 'Errore');
    showAlertSafe('danger', error.message);
  }
};

window.verificaForm = async function() {
  updateBadge('formStatus', 'loading', 'Verifica in corso...');
  try {
    const data = await postAdminAction('check_form_status');
    if (data.success && data.event) {
      updateBadge('formStatus', true, 'Operativo');
      const { titolo, event_date: eventDate } = data.event;
      const eventLabel = eventDate ? `${new Date(eventDate).toLocaleDateString('it-IT')} - ${titolo}` : titolo;
      showAlertSafe('success', `Form attivo. Evento disponibile: ${eventLabel}`);
    } else {
      throw new Error(data.error || 'Form non disponibile');
    }
  } catch (error) {
    updateBadge('formStatus', false, 'Errore');
    showAlertSafe('danger', error.message);
  }
};

// ===== HERO CAROUSEL =====
window.cleanHeroCarousel = async function() {
  try {
    const data = await postAdminAction('clean_hero_carousel');
    const heroResults = document.getElementById('heroCleanResults');
    const heroContent = document.getElementById('heroCleanContent');
    if (heroResults) heroResults.style.display = 'block';
    if (heroContent) {
      heroContent.textContent = data.success ? (data.message || 'Pulizia completata con successo.') : (data.error || 'Errore durante la pulizia.');
    }

    if (data.success) {
      showAlertSafe('success', data.message || 'Pulizia carosello completata');
    } else {
      throw new Error(data.error || 'Impossibile pulire il carosello');
    }
  } catch (error) {
    showAlertSafe('danger', error.message);
  }
};

window.closeHeroResults = function() {
  toggleElementDisplay('heroCleanResults', false);
};

// ===== EMAIL TEXTS MANAGEMENT =====
window.loadEmailTexts = async function() {
  try {
    const data = await postAdminAction('get_email_texts');
    if (!data.success || !data.texts) {
      throw new Error(data.error || 'Impossibile caricare i testi email');
    }

    cachedEmailTexts = data.texts;
    populateEmailForm(data.texts);
    toggleElementDisplay('emailEditor', true);
    toggleElementDisplay('emailPreview', false);
    showAlertSafe('info', 'Testi email caricati. Ricordati di salvare dopo le modifiche.');
  } catch (error) {
    showAlertSafe('danger', error.message);
  }
};

window.resetToDefaults = function() {
  populateEmailForm(defaultEmailTexts);
  showAlertSafe('info', 'Valori predefiniti ripristinati. Non dimenticare di salvare per applicare le modifiche.');
};

window.saveEmailTexts = async function() {
  const texts = getEmailFormValues();
  try {
    const data = await postAdminAction('save_email_texts', { texts });
    if (data.success) {
      cachedEmailTexts = { ...texts };
      showAlertSafe('success', data.message || 'Testi email salvati con successo.');
    } else {
      throw new Error(data.error || 'Errore durante il salvataggio dei testi');
    }
  } catch (error) {
    showAlertSafe('danger', error.message);
  }
};

window.previewEmail = async function() {
  const texts = getEmailFormValues();
  const frame = document.getElementById('emailPreviewFrame');
  if (!frame) return;

  frame.srcdoc = buildEmailPreviewHtml(texts);
  toggleElementDisplay('emailPreview', true);
};

window.closeEmailEditor = function() {
  toggleElementDisplay('emailEditor', false);
};

window.closeEmailPreview = function() {
  toggleElementDisplay('emailPreview', false);
};

window.exportTexts = async function() {
  try {
    let texts = cachedEmailTexts;
    if (!texts) {
      const data = await postAdminAction('get_email_texts');
      if (!data.success || !data.texts) {
        throw new Error(data.error || 'Impossibile esportare i testi');
      }
      texts = data.texts;
    }

    const blob = new Blob([JSON.stringify(texts, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `email_texts_${new Date().toISOString().replace(/[:.]/g, '-')}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    showAlertSafe('success', 'Testi email esportati con successo.');
  } catch (error) {
    showAlertSafe('danger', error.message);
  }
};
