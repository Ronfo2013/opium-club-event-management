<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Admin - Gestione Eventi</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="./assets/css/style.css" rel="stylesheet">
</head>
<body>
    <header class="bg-primary text-white py-3 mb-4">
        <div class="container d-flex justify-content-between align-items-center">
            <h1 class="m-0">Gestione Eventi</h1>
            <a href="./logout" class="btn btn-outline-light">Logout</a>
        </div>
    </header>

    <div class="container">
        <?php if (!empty($this->successMessage)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($this->successMessage); ?></div>
        <?php endif; ?>

        <?php if (!empty($this->errorMessage)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($this->errorMessage); ?></div>
        <?php endif; ?>

        <!-- Form Aggiungi/Modifica Evento -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title m-0"><?php echo isset($editEvent) && $editEvent ? 'Modifica Evento' : 'Aggiungi Evento'; ?></h3>
            </div>
            <div class="card-body">
                <form method="POST" action="./admin">
                    <?php if (isset($editEvent) && $editEvent): ?>
                        <!-- Campo nascosto con l'ID dell'evento per la modifica -->
                        <input type="hidden" name="edit_index" value="<?php echo intval($editEvent['id']); ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="data" class="form-label">Data (YYYY-MM-DD)</label>
                        <input type="date" class="form-control" id="data" name="data" required
                               value="<?php 
                                 if (isset($editEvent) && $editEvent) {
                                     // Convertiamo la data da dd-mm-yyyy a yyyy-mm-dd
                                     $dateObj = DateTime::createFromFormat('d-m-Y', $editEvent['date']);
                                     echo $dateObj ? $dateObj->format('Y-m-d') : '';
                                 } 
                               ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="titolo" class="form-label">Titolo</label>
                        <input type="text" class="form-control" id="titolo" name="titolo" required
                               value="<?php echo isset($editEvent) && $editEvent ? htmlspecialchars($editEvent['titolo']) : ''; ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <?php echo (isset($editEvent) && $editEvent) ? 'Aggiorna' : 'Aggiungi'; ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Statistiche Eventi -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">Statistiche Eventi</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="./admin" class="mb-3">
                    <div class="row align-items-end">
                        <div class="col-md-6">
                            <label for="filter_evento" class="form-label">Seleziona Evento</label>
                            <select name="filter_evento" id="filter_evento" class="form-select">
                                <option value="">Seleziona...</option>
                                <?php foreach ($events as $eventItem): ?>
                            <option value="<?php echo htmlspecialchars($eventItem['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                         <?php echo htmlspecialchars($eventItem['titolo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filtra</button>
                        </div>
                    </div>
                </form>

                <?php if ($stats): ?>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Totale Iscritti</h5>
                                    <p class="card-text fs-4"><?php echo $stats['totIscritti']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Validati</h5>
                                    <p class="card-text fs-4"><?php echo $stats['totValidati']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Non Validati</h5>
                                    <p class="card-text fs-4"><?php echo $stats['totNonValidi']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lista Eventi -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">Lista Eventi</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th> <!-- Aggiunta colonna ID -->
                                <th>Data</th>
                                <th>Titolo</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $eventIndex => $eventItem): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($eventItem['id']); ?></td> <!-- Stampa ID evento -->
                                    <td><?php echo htmlspecialchars($eventItem['date']); ?></td>
                                    <td><?php echo htmlspecialchars($eventItem['titolo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <a href="./admin?edit=<?php echo $eventItem['id']; ?>" 
                                           class="btn btn-sm btn-warning">Modifica</a>
                                        <a href="./admin?delete=<?php echo $eventItem['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Sei sicuro di voler eliminare questo evento?');">
                                           Elimina
                                        </a>
                                        <a href="./admin?details_event=<?php echo urlencode($eventItem['id']); ?>" 
                                           class="btn btn-sm btn-info">Dettagli</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Ricerca Utenti -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">Ricerca Utenti</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="./admin" class="mb-3">
                    <div class="row">
                        <div class="col-md-10">
                            <input type="text" name="q_user" class="form-control" 
                                   placeholder="Cerca per nome, cognome, email o telefono"
                                   value="<?php echo htmlspecialchars($searchUser); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Cerca</button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($searchResults)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Cognome</th>
                                    <th>Email</th>
                                    <th>Telefono</th>
                                    <th>Evento</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($searchResults as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($user['cognome']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['telefono']); ?></td>
                                        <td><?php echo htmlspecialchars($user['evento']); ?></td>
                                        <td>
                                            <?php if ($user['validato']): ?>
                                                <span class="badge bg-success">Validato</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Non Validato</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['validato']): ?>
                                                <a href="./admin?invalidate=<?php echo urlencode($user['token']); ?>" 
                                                   class="btn btn-sm btn-warning"
                                                   onclick="return confirm('Sei sicuro di voler invalidare questo QR Code?');">
                                                    Invalida QR
                                                </a>
                                            <?php endif; ?>
                                            <a href="./admin?delete_user=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Sei sicuro di voler eliminare questo utente? L\'azione è irreversibile.');">
                                                Elimina Utente
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dettagli Evento -->
        <?php if (!empty($detailsUsers)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title m-0">Dettagli Evento: <?php echo htmlspecialchars($detailsEvent); ?></h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Cognome</th>
                                    <th>Email</th>
                                    <th>Telefono</th>
                                    <th>Data Registrazione</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detailsUsers as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($user['cognome']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['telefono']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <?php if ($user['validato']): ?>
                                                <span class="badge bg-success">Validato</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Non Validato</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['validato']): ?>
                                                <a href="./admin?details_event=<?php echo urlencode($detailsEvent); ?>&invalidate=<?php echo urlencode($user['token']); ?>" 
                                                   class="btn btn-sm btn-warning"
                                                   onclick="return confirm('Sei sicuro di voler invalidare questo QR Code per l\'evento?');">
                                                    Invalida QR
                                                </a>
                                            <?php endif; ?>
                                            <a href="./admin?details_event=<?php echo urlencode($detailsEvent); ?>&delete_user=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Sei sicuro di voler eliminare questo utente da questo evento? L\'azione è irreversibile.');">
                                                Elimina Utente
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>