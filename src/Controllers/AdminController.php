<?php

namespace App\Controllers;

class AdminController {
    private $pdo;
    private $successMessage = '';
    private $errorMessage = '';

    public function __construct($pdo, $config) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    
        if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
            header("Location: /login");
            exit;
        }
    
        $this->pdo = $pdo;
    }

    /**
     * Metodo principale: mostra la pagina admin, gestisce i parametri GET/POST
     */
    public function index() {
        // Se l'utente ha inviato il form di aggiunta/modifica evento
        if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['titolo'])) {
            $this->handleEventSubmission();
        }

        // Se l'utente ha cliccato per eliminare un evento
        if (isset($_GET['delete'])) {
            $this->deleteEvent(intval($_GET['delete']));
        }

        // Se l'utente vuole invalidare un QR
        if (isset($_GET['invalidate'])) {
            $this->invalidateQRCode($_GET['invalidate']);
        }
        // Se l'utente vuole eliminare un utente
        if (isset($_GET['delete_user'])) {
            $this->deleteUser(intval($_GET['delete_user']));
        }

        // Carico tutti gli eventi dal DB
        $events = $this->readEvents();

        // Parametri di filtraggio/ricerca
        $selectedEvent = $_GET['filter_evento'] ?? '';
        $searchUser = $_GET['q_user'] ?? '';
        $detailsEvent = $_GET['details_event'] ?? '';

        // Calcolo statistiche se c’è un evento selezionato
        $stats = !empty($selectedEvent) ? $this->getEventStatistics($selectedEvent) : null;

        // Dettagli utenti per un determinato evento
        $detailsUsers = !empty($detailsEvent) ? $this->getEventUsers($detailsEvent) : [];

        // Ricerca utenti generica
        $searchResults = !empty($searchUser) ? $this->searchUsers($searchUser) : [];

        // Se c'è un evento da modificare, carico i dati
        $editEvent = null;
        if (isset($_GET['edit'])) {
            $editId = intval($_GET['edit']);
            $editEvent = $this->findEventById($editId);

                // Crea variabili normali da passare alla view
    $successMessage = $this->successMessage;
    $errorMessage   = $this->errorMessage;

    // Altre variabili da passare
    $events         = $this->readEvents();
    $selectedEvent  = $_GET['filter_evento'] ?? '';
    $searchUser     = $_GET['q_user'] ?? '';
    $detailsEvent   = $_GET['details_event'] ?? '';
    $stats          = !empty($selectedEvent) ? $this->getEventStatistics($selectedEvent) : null;
    $detailsUsers   = !empty($detailsEvent) ? $this->getEventUsers($detailsEvent) : [];
    $searchResults  = !empty($searchUser) ? $this->searchUsers($searchUser) : [];
    $editEvent      = null;
    if (isset($_GET['edit'])) {
        $editId   = intval($_GET['edit']);
        $editEvent = $this->findEventById($editId);
    }
        }

        // Mostro la vista admin
        require __DIR__ . '/../Views/admin.php';
    }

    /**
     * Legge tutti gli eventi dal DB (versione aggiornata)
     */
    private function readEvents() {
        try {
            $stmt = $this->pdo->query("SELECT id, event_date, titolo, chiuso FROM events ORDER BY event_date ASC");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $events = [];
            foreach ($rows as $row) {
                $events[] = [
                    'id' => $row['id'],  // ID numerico dell'evento
                    'event_date' => $row['event_date'],  // data in formato Y-m-d
                    'date' => !empty($row['event_date']) ? date('d-m-Y', strtotime($row['event_date'])) : '',
                    'titolo' => $row['titolo'],
                    'chiuso' => isset($row['chiuso']) ? (int)$row['chiuso'] : 0
                ];
            }
            return $events;
        } catch (\PDOException $e) {
            $this->errorMessage = "Errore nel recupero degli eventi: " . $e->getMessage();
            return [];
        }
    }

    /**
     * Trova un singolo evento dal DB in base all'id
     */
    private function findEventById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, event_date, titolo FROM events WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'id' => $row['id'],
                    'date' => date('d-m-Y', strtotime($row['event_date'])),
                    'titolo' => $row['titolo']
                ];
            }
        } catch (\PDOException $e) {
            $this->errorMessage = "Errore nel recupero dell'evento: " . $e->getMessage();
        }
        return null;
    }

    /**
     * Inserisce un nuovo evento
     */
    private function createEvent($eventDate, $titolo) {
        try {
            // Controllo se l'evento esiste già
            $checkStmt = $this->pdo->prepare("SELECT id FROM events WHERE event_date = :event_date AND titolo = :titolo LIMIT 1");
            $checkStmt->execute([':event_date' => $eventDate, ':titolo' => $titolo]);
            $existingEvent = $checkStmt->fetch(\PDO::FETCH_ASSOC);

            if ($existingEvent) {
                $this->successMessage = "L'evento esiste già!";
                return;
            }

            // Creiamo solo se non esiste già
            $stmt = $this->pdo->prepare("INSERT INTO events (event_date, titolo) VALUES (:event_date, :titolo)");
            $stmt->execute([
                ':event_date' => $eventDate,
                ':titolo' => $titolo
            ]);
            $this->successMessage = "Evento aggiunto correttamente!";
        } catch (\PDOException $e) {
            $this->errorMessage = "Errore durante l'inserimento dell'evento: " . $e->getMessage();
        }
    }

    /**
     * Aggiorna un evento esistente
     */
    private function updateEvent($id, $eventDate, $titolo) {
        try {
            $stmt = $this->pdo->prepare("UPDATE events SET event_date = :event_date, titolo = :titolo WHERE id = :id");
            $stmt->execute([
                ':event_date' => $eventDate,
                ':titolo' => $titolo,
                ':id' => $id
            ]);
            $this->successMessage = "Evento modificato correttamente!";
        } catch (\PDOException $e) {
            $this->errorMessage = "Errore durante l'aggiornamento dell'evento: " . $e->getMessage();
        }
    }

    /**
     * Elimina un evento dal DB
     */
    private function deleteEvent($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM events WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $this->successMessage = "Evento cancellato correttamente!";
        } catch (\PDOException $e) {
            $this->errorMessage = "Errore durante la cancellazione dell'evento: " . $e->getMessage();
        }
    }

    /**
     * Elimina un utente dal DB
     */
    private function deleteUser($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM utenti WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $this->successMessage = "Utente cancellato correttamente!";
        } catch (\PDOException $e) {
            $this->errorMessage = "Errore durante la cancellazione dell'utente: " . $e->getMessage();
        }
    }

    /**
     * Gestisce la creazione o l'aggiornamento di un evento (POST)
     */
    private function handleEventSubmission() {
        $editId = isset($_POST['edit_index']) ? intval($_POST['edit_index']) : null;
        $dataInput = $_POST["data"] ?? '';
        $titolo = $_POST["titolo"] ?? '';

        if (empty($dataInput) || empty($titolo)) {
            $this->errorMessage = "Tutti i campi sono obbligatori!";
            return;
        }

        // Converte la data da yyyy-mm-dd a yyyy-mm-dd (o la validiamo)
        $dateTime = \DateTime::createFromFormat('Y-m-d', $dataInput);
        if (!$dateTime) {
            $this->errorMessage = "Formato data non valido!";
            return;
        }

        $eventDate = $dateTime->format('Y-m-d');

        // Se esiste un editId, aggiorno; altrimenti creo un nuovo evento
        if ($editId) {
            $this->updateEvent($editId, $eventDate, $titolo);
        } else {
            $this->createEvent($eventDate, $titolo);
        }
    }

    /**
     * Invalida un QR code (aggiorna colonna validato=0 su tabella utenti)
     */
    private function invalidateQRCode($token) {
        try {
            $stmt = $this->pdo->prepare("UPDATE utenti SET validato=0 WHERE token = :token");
            $stmt->execute([':token' => $token]);
            $this->successMessage = "QR Code invalidato con successo!";
        } catch (\PDOException $e) {
            $this->errorMessage = "Errore durante l'invalidamento del QR code: " . $e->getMessage();
        }
    }

    /**
     * Calcola statistiche su un determinato evento
     */
    private function getEventStatistics($eventoId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) AS tot,
                    SUM(CASE WHEN validato = 1 THEN 1 ELSE 0 END) AS val
                FROM utenti
                WHERE evento = :evento
            ");
            $stmt->execute([":evento" => $eventoId]);
            $stat = $stmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'totIscritti' => $stat['tot'],
                'totValidati' => $stat['val'],
                'totNonValidi' => $stat['tot'] - $stat['val']
            ];
        } catch (\PDOException $e) {
            $this->errorMessage = "Errore nel calcolo delle statistiche: " . $e->getMessage();
            return null;
        }
    }

    /**
     * Restituisce gli utenti di un determinato evento
     */
    private function getEventUsers($eventoTitolo) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM utenti WHERE evento = :ev ORDER BY created_at DESC");
            $stmt->execute([':ev' => $eventoTitolo]);
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            error_log("👥 Utenti trovati: " . print_r($users, true));
            return $users;
        } catch (\PDOException $e) {
            $this->errorMessage = "❌ Errore nel recupero utenti: " . $e->getMessage();
            return [];
        }
    }

    /**
     * Ricerca utenti su campi (nome, cognome, email, telefono)
     */
    private function searchUsers($searchTerm) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM utenti 
                WHERE nome LIKE :search 
                OR cognome LIKE :search 
                OR email LIKE :search 
                OR telefono LIKE :search
                ORDER BY created_at DESC
            ");
            $searchTerm = "%$searchTerm%";
            $stmt->execute([':search' => $searchTerm]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $this->errorMessage = "Errore nella ricerca utenti: " . $e->getMessage();
            return [];
        }
    }
}
