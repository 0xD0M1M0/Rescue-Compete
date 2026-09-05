<?php

namespace FormCollection;

use FormCollection\FormCollectionModel;
use PDO;
use PDOException;
use Exception;

class FormCollectionController
{
    private FormCollectionModel $model;
    private PDO $db;

    // Public Properties für View-Zugriff
    public array $collections = [];
    public array $questionPools = [];
    public array $stations = [];
    public string $message = '';
    public string $messageType = 'info';
    public ?array $currentCollection = null;
    public array $selectedQuestions = [];
    public array $collectionTokens = [];
    public array $performanceStats = [];
    public array $teamProgress = [];
    public array $teamFormDetails = [];
    public array $validationErrors = [];
    public ?string $currentView = null;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->model = new FormCollectionModel($db);
    }

    /**
     * Hauptmethod zur Verarbeitung aller Requests
     */
    public function handleRequest(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
            enforce_post_same_origin();
        }
        // AJAX-Requests zuerst behandeln (bevor HTML-Output beginnt)
        if ($this->isAjaxRequest()) {
            $this->handleAjaxRequest();
            return; // Wichtig: Stoppt weitere Verarbeitung
        }

        // Standard-Daten laden mit verbessertem Error Handling
        $this->loadBaseData();

        // POST-Requests verarbeiten
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePostRequest();
        }

        // GET-Requests verarbeiten
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->handleGetRequest();
        }
    }

    /**
     * Prüft ob es sich um einen AJAX-Request handelt
     */
    private function isAjaxRequest(): bool
    {
        return (isset($_GET['ajax']) && $_GET['ajax'] === '1') ||
            (isset($_POST['ajax']) && $_POST['ajax'] === '1') ||
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * Behandelt AJAX-Requests
     */
    private function handleAjaxRequest(): void
    {
        try {
            $action = $_GET['action'] ?? $_POST['action'] ?? '';

            switch ($action) {
                case 'load_questions':
                    $this->handleLoadQuestionsAjax();
                    break;

                case 'check_name':
                    $this->handleCheckNameAjax();
                    break;

                default:
                    $this->sendJsonResponse(false, 'Unbekannte AJAX-Aktion.');
            }
        } catch (Exception $e) {
            error_log("Error in FormCollectionController::handleAjaxRequest: " . $e->getMessage());
            $this->sendJsonResponse(false, 'Ein unerwarteter Fehler ist aufgetreten.');
        }
    }

    /**
     * Lädt grundlegende Daten für die View
     * VERBESSERT: Robusteres Error Handling und Debug-Logging
     */
    private function loadBaseData(): void
    {
        try {
            error_log("FormCollectionController::loadBaseData - Starting data load");

            // Collections laden mit Fallback
            $this->collections = $this->model->readCollection();
            if ($this->collections === null) {
                error_log("FormCollectionController::loadBaseData - Failed to load collections, setting empty array");
                $this->collections = [];
                $this->message = 'Hinweis: Collections konnten nicht vollständig geladen werden.';
                $this->messageType = 'error';
            } else {
                error_log("FormCollectionController::loadBaseData - Successfully loaded " . count($this->collections) . " collections");
            }

            // Question Pools laden
            $this->questionPools = $this->model->getAvailableQuestionPools();
            if (empty($this->questionPools)) {
                error_log("FormCollectionController::loadBaseData - No question pools found");
            } else {
                error_log("FormCollectionController::loadBaseData - Loaded " . count($this->questionPools) . " question pools");
            }

            // Stationen laden
            $this->stations = $this->model->getAvailableStations();
            if (empty($this->stations)) {
                error_log("FormCollectionController::loadBaseData - No stations found");
            } else {
                error_log("FormCollectionController::loadBaseData - Loaded " . count($this->stations) . " stations");
            }

            // Performance Stats laden (mit Fallback)
            $this->performanceStats = $this->model->getCollectionPerformance();
            if ($this->performanceStats === null) {
                error_log("FormCollectionController::loadBaseData - Failed to load performance stats, setting empty array");
                $this->performanceStats = [];
            } else {
                error_log("FormCollectionController::loadBaseData - Loaded " . count($this->performanceStats) . " performance records");
            }

            // Team Progress laden (mit Fallback)
            $this->teamProgress = $this->model->getTeamCollectionProgress();
            if ($this->teamProgress === null) {
                error_log("FormCollectionController::loadBaseData - Failed to load team progress, setting empty array");
                $this->teamProgress = [];
            } else {
                error_log("FormCollectionController::loadBaseData - Loaded " . count($this->teamProgress) . " team progress records");
            }

            // Team-Formular-Details laden
            $this->teamFormDetails = $this->model->getTeamFormDetails();

            error_log("FormCollectionController::loadBaseData - Data loading completed");

        } catch (Exception $e) {
            error_log("Error in FormCollectionController::loadBaseData: " . $e->getMessage());

            // Fallback: Leere Arrays setzen statt null
            if (!is_array($this->collections)) $this->collections = [];
            if (!is_array($this->questionPools)) $this->questionPools = [];
            if (!is_array($this->stations)) $this->stations = [];
            if (!is_array($this->performanceStats)) $this->performanceStats = [];
            if (!is_array($this->teamProgress)) $this->teamProgress = [];
            if (!is_array($this->teamFormDetails)) $this->teamFormDetails = [];

            $this->message = 'Fehler beim Laden der Daten. Bitte versuchen Sie es erneut oder kontaktieren Sie den Administrator.';
            $this->messageType = 'error';
        }
    }

    /**
     * Verarbeitet POST-Requests
     */
    private function handlePostRequest(): void
    {
        $action = $_POST['action'] ?? '';
        error_log("FormCollectionController::handlePostRequest - Action: {$action}");

        switch ($action) {
            case 'create_collection':
                $this->handleCreateCollection();
                break;

            case 'delete_collection':
                $this->handleDeleteCollection();
                break;

            case 'process_expired':
                $this->handleProcessExpired();
                break;

            case 'update_time_limit':
                $this->handleUpdateTimeLimit();
                break;

            default:
                error_log("FormCollectionController::handlePostRequest - Unknown action: {$action}");
                $this->message = 'Unbekannte Aktion.';
                $this->messageType = 'error';
        }
    }

    /**
     * Verarbeitet GET-Requests
     */
    private function handleGetRequest(): void
    {
        $action = $_GET['action'] ?? '';
        if (!empty($action)) {
            error_log("FormCollectionController::handleGetRequest - Action: {$action}");
        }

        switch ($action) {
            case 'view_collection':
                $this->handleViewCollection();
                break;

            case 'view_tokens':
                $this->handleViewTokens();
                break;

            default:
                // Keine spezielle Aktion - Standard-Daten sind bereits geladen
                break;
        }
    }

    /**
     * Erstellt eine neue FormCollection
     */
    private function handleCreateCollection(): void
    {
        try {
            error_log("FormCollectionController::handleCreateCollection - Starting collection creation");

            // Input-Validierung
            $requiredFields = ['name', 'question_pool', 'forms_count', 'time_limit'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    error_log("FormCollectionController::handleCreateCollection - Missing required field: {$field}");
                    $this->message = "Feld '{$field}' ist erforderlich.";
                    $this->messageType = 'error';
                    return;
                }
            }

            // Fragen-IDs validieren
            if (empty($_POST['question_ids']) || !is_array($_POST['question_ids'])) {
                error_log("FormCollectionController::handleCreateCollection - No questions selected or invalid format");
                $this->message = 'Bitte wählen Sie mindestens eine Frage aus.';
                $this->messageType = 'error';
                return;
            }

            $questionIds = array_map('intval', $_POST['question_ids']);
            $formsCount = intval($_POST['forms_count']);

            error_log("FormCollectionController::handleCreateCollection - Processing " . count($questionIds) . " questions for {$formsCount} forms");

            // Collection-Daten zusammenstellen
            $collectionData = [
                'name' => trim($_POST['name']),
                'description' => trim($_POST['description'] ?? ''),
                'timeLimit' => intval($_POST['time_limit']),
                'totalQuestions' => count($questionIds),
                'formsCount' => $formsCount,
                'station_ID' => !empty($_POST['station_id']) ? intval($_POST['station_id']) : null
            ];

            // Model-Validierung durchführen
            $this->validationErrors = $this->model->validateCollectionData($collectionData);

            if (!empty($this->validationErrors)) {
                error_log("FormCollectionController::handleCreateCollection - Validation errors: " . json_encode($this->validationErrors));
                $this->message = 'Bitte korrigieren Sie die Eingabefehler.';
                $this->messageType = 'error';
                return;
            }

            // Collection erstellen
            $collectionId = $this->model->createCollection($collectionData, $questionIds);

            if ($collectionId) {
                error_log("FormCollectionController::handleCreateCollection - Successfully created collection ID: {$collectionId}");
                $this->message = "Formular-Gruppe '{$collectionData['name']}' wurde erfolgreich erstellt.";
                $this->messageType = 'success';

                // Daten neu laden
                $this->loadBaseData();

                // Validierungsfehler zurücksetzen nach erfolgreichem Erstellen
                $this->validationErrors = [];
            } else {
                error_log("FormCollectionController::handleCreateCollection - Failed to create collection");
                $this->message = 'Fehler beim Erstellen der Formular-Gruppe.';
                $this->messageType = 'error';
            }

        } catch (Exception $e) {
            error_log("Error in FormCollectionController::handleCreateCollection: " . $e->getMessage());
            $this->message = 'Ein unerwarteter Fehler ist aufgetreten.';
            $this->messageType = 'error';
        }
    }

    /**
     * Löscht eine FormCollection
     */
    private function handleDeleteCollection(): void
    {
        try {
            $collectionId = intval($_POST['collection_id'] ?? 0);
            $confirmDelete = $_POST['confirm_delete'] ?? '';

            error_log("FormCollectionController::handleDeleteCollection - Attempting to delete collection ID: {$collectionId}");

            if ($collectionId <= 0) {
                error_log("FormCollectionController::handleDeleteCollection - Invalid collection ID: {$collectionId}");
                $this->message = 'Ungültige Formular-Gruppen-ID.';
                $this->messageType = 'error';
                return;
            }

            if ($confirmDelete !== '1') {
                error_log("FormCollectionController::handleDeleteCollection - Delete not confirmed");
                $this->message = 'Löschung nicht bestätigt.';
                $this->messageType = 'error';
                return;
            }

            // Collection-Name für Meldung abrufen
            $collection = $this->model->readCollection($collectionId);
            $collectionName = $collection['name'] ?? 'Unbekannt';

            // Löschen
            if ($this->model->deleteCollection($collectionId)) {
                error_log("FormCollectionController::handleDeleteCollection - Successfully deleted collection: {$collectionName}");
                $this->message = "Formular-Gruppe '{$collectionName}' wurde erfolgreich gelöscht.";
                $this->messageType = 'success';

                // Daten neu laden
                $this->loadBaseData();
            } else {
                error_log("FormCollectionController::handleDeleteCollection - Failed to delete collection ID: {$collectionId}");
                $this->message = 'Fehler beim Löschen der Formular-Gruppe.';
                $this->messageType = 'error';
            }

        } catch (Exception $e) {
            error_log("Error in FormCollectionController::handleDeleteCollection: " . $e->getMessage());
            $this->message = 'Ein unerwarteter Fehler ist aufgetreten.';
            $this->messageType = 'error';
        }
    }

    /**
     * Verarbeitet abgelaufene Formulare
     */
    private function handleProcessExpired(): void
    {
        try {
            error_log("FormCollectionController::handleProcessExpired - Processing expired forms");

            $stats = $this->model->processExpiredInstances();

            if ($stats['errors'] > 0) {
                error_log("FormCollectionController::handleProcessExpired - Completed with errors: {$stats['errors']}");
                $this->message = "Verarbeitung abgeschlossen. {$stats['expired']} Formulare abgeschlossen, {$stats['errors']} Fehler.";
                $this->messageType = 'error';
            } else {
                error_log("FormCollectionController::handleProcessExpired - Successfully processed {$stats['expired']} forms");
                $this->message = "Erfolgreich {$stats['expired']} von {$stats['processed']} abgelaufenen Formularen verarbeitet.";
                $this->messageType = 'success';
            }

        } catch (Exception $e) {
            error_log("Error in FormCollectionController::handleProcessExpired: " . $e->getMessage());
            $this->message = 'Fehler beim Verarbeiten abgelaufener Formulare.';
            $this->messageType = 'error';
        }
    }

    /**
     * Aktualisiert das Zeitlimit einer bestehenden FormCollection.
     * Laufende Instanzen sind durch ihren Snapshot in TeamFormInstance.timeLimit geschützt.
     */
    private function handleUpdateTimeLimit(): void
    {
        // Bei dieser Action gehört der Edit-Tab auf den Bildschirm
        $this->currentView = 'edit';

        try {
            $collectionId = intval($_POST['collection_id'] ?? 0);
            $newTimeLimit = intval($_POST['time_limit'] ?? 0);

            if ($collectionId <= 0) {
                $this->message = 'Bitte wählen Sie eine Formular-Gruppe aus.';
                $this->messageType = 'error';
                return;
            }

            if ($newTimeLimit < 10 || $newTimeLimit > 1800) {
                $this->validationErrors['timeLimit'] = 'Das Zeitlimit muss zwischen 10 und 1800 Sekunden liegen.';
                $this->message = 'Bitte korrigieren Sie das Zeitlimit.';
                $this->messageType = 'error';
                return;
            }

            $result = $this->model->updateTimeLimit($collectionId, $newTimeLimit);

            if (!$result['success']) {
                $this->message = $result['error'] ?? 'Fehler beim Aktualisieren des Zeitlimits.';
                $this->messageType = 'error';
                return;
            }

            $running = (int)$result['runningSnapshotted'];
            $base = "Zeitlimit erfolgreich auf {$newTimeLimit} Sekunden aktualisiert.";
            if ($running > 0) {
                $this->message = "{$base} {$running} bereits laufende Formular(e) behalten ihr ursprüngliches Zeitlimit.";
            } else {
                $this->message = $base;
            }
            $this->messageType = 'success';

            // Aktualisierte Daten neu laden
            $this->loadBaseData();
            $this->validationErrors = [];
        } catch (Exception $e) {
            error_log("Error in FormCollectionController::handleUpdateTimeLimit: " . $e->getMessage());
            $this->message = 'Ein unerwarteter Fehler ist aufgetreten.';
            $this->messageType = 'error';
        }
    }

    /**
     * Zeigt Collection-Details an
     */
    private function handleViewCollection(): void
    {
        try {
            $collectionId = intval($_GET['collection_id'] ?? 0);

            error_log("FormCollectionController::handleViewCollection - Loading collection ID: {$collectionId}");

            if ($collectionId <= 0) {
                error_log("FormCollectionController::handleViewCollection - Invalid collection ID: {$collectionId}");
                $this->message = 'Ungültige Formular-Gruppen-ID.';
                $this->messageType = 'error';
                return;
            }

            $this->currentCollection = $this->model->readCollection($collectionId);

            if (!$this->currentCollection) {
                error_log("FormCollectionController::handleViewCollection - Collection not found: {$collectionId}");
                $this->message = 'Formular-Gruppe nicht gefunden.';
                $this->messageType = 'error';
                return;
            }

            $this->selectedQuestions = $this->model->getCollectionQuestions($collectionId);
            error_log("FormCollectionController::handleViewCollection - Loaded collection '{$this->currentCollection['name']}' with " . count($this->selectedQuestions) . " questions");

        } catch (Exception $e) {
            error_log("Error in FormCollectionController::handleViewCollection: " . $e->getMessage());
            $this->message = 'Fehler beim Laden der Formular-Gruppen-Details.';
            $this->messageType = 'error';
        }
    }

    /**
     * Zeigt QR-Code-Tokens einer Collection an
     */
    private function handleViewTokens(): void
    {
        try {
            $collectionId = intval($_GET['collection_id'] ?? 0);

            error_log("FormCollectionController::handleViewTokens - Loading tokens for collection ID: {$collectionId}");

            if ($collectionId <= 0) {
                error_log("FormCollectionController::handleViewTokens - Invalid collection ID: {$collectionId}");
                $this->message = 'Ungültige Formular-Gruppen-ID.';
                $this->messageType = 'error';
                return;
            }

            // Basis-URL für QR-Codes
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $baseUrl = $protocol . $_SERVER['HTTP_HOST'];

            $this->collectionTokens = $this->model->getCollectionTokens($collectionId, $baseUrl);

            if (!empty($this->collectionTokens)) {
                error_log("FormCollectionController::handleViewTokens - Loaded " . count($this->collectionTokens) . " tokens");
            } else {
                error_log("FormCollectionController::handleViewTokens - No tokens found for collection {$collectionId}");
            }

        } catch (Exception $e) {
            error_log("Error in FormCollectionController::handleViewTokens: " . $e->getMessage());
            $this->message = 'Fehler beim Laden der QR-Code-Tokens.';
            $this->messageType = 'error';
        }
    }

    /**
     * Lädt Fragen für AJAX-Request und gibt JSON zurück
     */
    private function handleLoadQuestionsAjax(): void
    {
        try {
            $poolId = intval($_GET['pool_id'] ?? 0);

            error_log("FormCollectionController::handleLoadQuestionsAjax - Loading questions for pool ID: {$poolId}");

            if ($poolId <= 0) {
                $this->sendJsonResponse(false, 'Ungültige Pool-ID.');
                return;
            }

            // Model-Methode aufrufen
            $questions = $this->model->getQuestionsByPool($poolId);

            if (empty($questions)) {
                error_log("FormCollectionController::handleLoadQuestionsAjax - No questions found for pool {$poolId}");
                $this->sendJsonResponse(true, 'Keine Fragen in diesem Pool gefunden.', [
                    'questions' => [],
                    'count' => 0
                ]);
                return;
            }

            error_log("FormCollectionController::handleLoadQuestionsAjax - Successfully loaded " . count($questions) . " questions");

            $this->sendJsonResponse(true, 'Fragen erfolgreich geladen.', [
                'questions' => $questions,
                'count' => count($questions)
            ]);

        } catch (Exception $e) {
            error_log("Error in FormCollectionController::handleLoadQuestionsAjax: " . $e->getMessage());
            $this->sendJsonResponse(false, 'Fehler beim Laden der Fragen.');
        }
    }

    /**
     * Prüft Namen-Duplikate für AJAX-Request
     */
    private function handleCheckNameAjax(): void
    {
        try {
            $name = trim($_GET['name'] ?? '');
            $excludeId = isset($_GET['exclude_id']) ? intval($_GET['exclude_id']) : null;

            if (empty($name)) {
                $this->sendJsonResponse(true, 'Kein Name angegeben.', ['exists' => false]);
                return;
            }

            $exists = $this->model->checkNameExists($name, $excludeId);

            error_log("FormCollectionController::handleCheckNameAjax - Name check for '{$name}': " . ($exists ? 'exists' : 'available'));

            $this->sendJsonResponse(true, $exists ? 'Name bereits vorhanden.' : 'Name verfügbar.', [
                'exists' => $exists,
                'name' => $name
            ]);

        } catch (Exception $e) {
            error_log("Error in FormCollectionController::handleCheckNameAjax: " . $e->getMessage());
            $this->sendJsonResponse(false, 'Fehler beim Prüfen des Namens.');
        }
    }

    /**
     * Sendet JSON-Response für AJAX-Requests
     */
    private function sendJsonResponse(bool $success, string $message, array $data = []): void
    {
        // Sicherstellen, dass keine vorherige Ausgabe erfolgt ist
        if (ob_get_level()) {
            ob_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

        $response = [
            'success' => $success,
            'message' => $message
        ];

        if (!empty($data)) {
            $response = array_merge($response, $data);
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Hilfsfunktion zum Validieren von Array-Eingaben
     */
    private function validateArrayInput(array $data, array $requiredFields): bool
    {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                return false;
            }
        }
        return true;
    }

    /**
     * Sanitisiert String-Eingaben
     */
    private function sanitizeString(string $input): string
    {
        return trim(filter_var($input, FILTER_SANITIZE_STRING));
    }

    /**
     * Validiert und sanitisiert Integer-Eingaben
     */
    private function sanitizeInt($input): int
    {
        return filter_var($input, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0]
        ]) ?: 0;
    }

    /**
     * Gibt Validierungsfehler für ein bestimmtes Feld zurück
     */
    public function getValidationError(string $field): string
    {
        return $this->validationErrors[$field] ?? '';
    }

    /**
     * Prüft ob ein bestimmtes Feld einen Validierungsfehler hat
     */
    public function hasValidationError(string $field): bool
    {
        return isset($this->validationErrors[$field]);
    }

    /**
     * Debug-Hilfsfunktion für Entwicklungsumgebung
     */
    private function debugLog(string $message, array $context = []): void
    {
        if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost') {
            error_log("FormCollectionController DEBUG: {$message}" .
                (!empty($context) ? " Context: " . json_encode($context) : ""));
        }
    }

    /**
     * Gibt Debug-Informationen über geladene Daten zurück
     */
    public function getDebugInfo(): array
    {
        return [
            'collections_count' => count($this->collections),
            'question_pools_count' => count($this->questionPools),
            'stations_count' => count($this->stations),
            'performance_stats_count' => count($this->performanceStats),
            'team_progress_count' => count($this->teamProgress),
            'has_message' => !empty($this->message),
            'message_type' => $this->messageType,
            'validation_errors_count' => count($this->validationErrors)
        ];
    }
}