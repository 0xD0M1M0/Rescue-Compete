<?php

namespace Scoring;

use model\ScoringModel;
use Exception;

class ScoringInputController {
    private ScoringModel $model;
    public string $message = "";
    public array $modalData = [];
    public string $redirectUrl = "ScoringInputView.php";

    public function __construct(ScoringModel $model) {
        $this->model = $model;

        // Session starten wenn noch nicht aktiv
        require_once __DIR__ . '/../CookieMonster.php';
        startSecureSession();
    }

    public function handleRequest() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
            enforce_post_same_origin();
        }

        // AJAX-Request für zugewiesene Teams
        if (isset($_GET['action']) && $_GET['action'] === 'getAssignedTeams') {
            $this->handleAjaxGetAssignedTeams();
            return;
        }

        // Löschen einer Wertungsklasse
        if (isset($_POST['delete_scoring'])) {
            $deleteId = intval($_POST['delete_id']);
            if ($this->model->delete($deleteId)) {
                $_SESSION['success_message'] = "Wertungsklasse erfolgreich gelöscht.";
                header("Location: " . $this->redirectUrl);
                exit;
            } else {
                $this->message = "Fehler beim Löschen der Wertung.";
            }
        }

        // Hinzufügen oder Aktualisieren einer Wertungsklasse
        if (isset($_POST['add_scoring'])) {
            $name = trim($_POST['name']);
            // Überprüfen, ob ein Duplikat-Update bestätigt wurde
            $confirmUpdate = isset($_POST['confirm_update']) && $_POST['confirm_update'] == "1";
            $providedDuplicateId = isset($_POST['duplicate_id']) ? intval($_POST['duplicate_id']) : null;
            $entry = ['name' => $name];
            $result = $this->model->addOrUpdateWertung($entry, $confirmUpdate, $providedDuplicateId);

            if ($result['status'] === 'duplicate') {
                // Statt einer Bestätigung zeigen wir hier einen einfachen Alert an
                $this->modalData = ['duplicate' => true];
            } elseif ($result['status'] === 'created' || $result['status'] === 'updated') {
                $_SESSION['success_message'] = $result['status'] === 'created'
                    ? "Wertungsklasse erfolgreich erstellt."
                    : "Wertungsklasse erfolgreich aktualisiert.";
                header("Location: " . $this->redirectUrl);
                exit;
            } else {
                $this->message = $result['message'];
            }
        }

        // Teams einer Wertungsklasse zuweisen (Checkbox-basiert: Zustand wird synchronisiert)
        if (isset($_POST['add_team'])) {
            $wertungsklasse = trim($_POST['wertung']);
            $wertungsID = $this->model->reverseRead($wertungsklasse);

            // Gewünschte Teams aus den Checkboxen sammeln
            $gewuenschteTeams = [];
            if (isset($_POST['teams']) && is_array($_POST['teams'])) {
                foreach ($_POST['teams'] as $teamData) {
                    if (!empty($teamData['name'])) {
                        $gewuenschteTeams[] = trim($teamData['name']);
                    }
                }
            }

            // Aktuell zugewiesene Teams abrufen
            $aktuelleTeams = $this->model->getAssignedTeams($wertungsklasse) ?? [];
            $aktuelleNamen = array_map(fn($t) => $t['Teamname'], $aktuelleTeams);

            // Teams entfernen, die nicht mehr angehakt sind
            $zuEntfernen = array_diff($aktuelleNamen, $gewuenschteTeams);
            foreach ($zuEntfernen as $teamname) {
                $mannschaftsID = $this->model->reverseReadMannschaft($teamname);
                $this->model->killMannschaftWertung($mannschaftsID, $wertungsID);
            }

            // Teams hinzufügen, die neu angehakt sind
            $hinzuzufuegen = array_diff($gewuenschteTeams, $aktuelleNamen);
            $successCount = 0;
            $errorCount = 0;
            foreach ($hinzuzufuegen as $teamname) {
                $mannschaftsID = $this->model->reverseReadMannschaft($teamname);
                $result = $this->model->MannschaftWertung($mannschaftsID, $wertungsID);
                if ($result !== false) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $this->message .= "Fehler beim Hinzufügen von Team: $teamname<br>";
                }
            }

            $removedCount = count($zuEntfernen);
            if ($errorCount === 0) {
                $messages = [];
                if ($successCount > 0) {
                    $messages[] = "$successCount " . ($successCount === 1 ? 'Team' : 'Teams') . " hinzugefügt";
                }
                if ($removedCount > 0) {
                    $messages[] = "$removedCount " . ($removedCount === 1 ? 'Team' : 'Teams') . " entfernt";
                }
                if (empty($messages)) {
                    $messages[] = "Keine Änderungen vorgenommen";
                }
                $_SESSION['success_message'] = implode(', ', $messages) . ".";
                header("Location: " . $this->redirectUrl . "?view=assign");
                exit;
            }
        }

        // Aufheben der Zuweisung zwischen Team und Wertungsklasse
        if (isset($_POST['cut_connection'])) {
            $teamname = trim($_POST['teamname']);
            $wertungsklasse = trim($_POST['wertung']);
            $mannschaftsID = $this->model->reverseReadMannschaft($teamname);
            $wertungsID = $this->model->reverseRead($wertungsklasse);

            if ($this->model->killMannschaftWertung($mannschaftsID, $wertungsID)) {
                $_SESSION['success_message'] = "Team erfolgreich aus der Wertungsklasse entfernt.";
                header("Location: " . $this->redirectUrl . "?view=remove");
                exit;
            } else {
                $this->message = "Fehler beim Entfernen des Teams.";
            }
        }

        // Entfernen ausgewählter Teams aus einer Wertungsklasse
        if (isset($_POST['remove_selected_teams'])) {
            $wertungsklasse = trim($_POST['wertung']);
            $selectedTeams = $_POST['selected_teams'] ?? [];

            if (empty($selectedTeams)) {
                $this->message = "Bitte wählen Sie mindestens ein Team zum Entfernen aus.";
                return;
            }

            // Konvertiere Team-IDs zu Integers
            $teamIds = array_map('intval', $selectedTeams);

            // Debug-Logging
            error_log("ScoringInputController: Entferne Teams aus Wertungsklasse: " . $wertungsklasse);
            error_log("ScoringInputController: Team-IDs: " . implode(', ', $teamIds));

            // Entferne Teams aus der Wertungsklasse
            $success = $this->model->removeMultipleTeamsFromWertung($teamIds, $wertungsklasse);

            if ($success) {
                $teamCount = count($teamIds);
                $teamText = $teamCount === 1 ? 'Team' : 'Teams';

                // Erfolgsmeldung in Session speichern
                $_SESSION['success_message'] = "$teamCount $teamText erfolgreich aus der Wertungsklasse entfernt.";
                $_SESSION['selected_wertung'] = $wertungsklasse; // Ausgewählte Wertung merken

                // Weiterleitung zur Remove-Ansicht
                header("Location: " . $this->redirectUrl . "?view=remove");
                exit;
            } else {
                $this->message = "Fehler beim Entfernen der Teams aus der Wertungsklasse.";
                error_log("ScoringInputController: Fehler beim Entfernen der Teams");
            }
        }
    }

    /**
     * Behandelt AJAX-Request für das Abrufen zugewiesener Teams
     */
    private function handleAjaxGetAssignedTeams(): void
    {
        // Content-Type für JSON-Antwort setzen
        header('Content-Type: application/json');

        try {
            // Parameter validieren
            if (!isset($_GET['wertung']) || empty(trim($_GET['wertung']))) {
                throw new Exception('Wertungsklasse nicht angegeben');
            }

            $wertungName = trim($_GET['wertung']);

            // Zugewiesene Teams abrufen
            $assignedTeams = $this->model->getAssignedTeams($wertungName);

            if ($assignedTeams === null) {
                throw new Exception('Fehler beim Abrufen der Teams');
            }

            // Erfolgreiche Antwort
            echo json_encode([
                'success' => true,
                'teams' => $assignedTeams,
                'wertung' => $wertungName,
                'count' => count($assignedTeams)
            ]);

        } catch (Exception $e) {
            // Fehler-Antwort
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Fehler beim Abrufen der Teams.'
            ]);

            // Fehler loggen
            error_log("ScoringInputController::handleAjaxGetAssignedTeams Error: " . $e->getMessage());
        }

        // Script beenden, da es sich um einen AJAX-Request handelt
        exit;
    }

    /**
     * Holt die zugewiesenen Teams für eine Wertungsklasse (für AJAX-Requests).
     *
     * @param string $wertungName Name der Wertungsklasse
     * @return array Array mit Team-Daten
     */
    public function getAssignedTeamsForWertung(string $wertungName): array
    {
        return $this->model->getAssignedTeams($wertungName) ?? [];
    }

    /**
     * Überprüft, ob eine Wertungsklasse Teams zugewiesen hat.
     *
     * @param string $wertungName Name der Wertungsklasse
     * @return bool
     */
    public function wertungHasTeams(string $wertungName): bool
    {
        return $this->model->hasAssignedTeams($wertungName);
    }
}