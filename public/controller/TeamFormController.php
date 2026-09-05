<?php
namespace TeamForm;

require_once '../db/DbConnection.php';
require_once '../model/TeamFormRelationModel.php';
require_once '../model/FormManagementModel.php';
require_once '../model/MannschaftModel.php';
require_once '../model/StationModel.php';

use PDO;
use PDOException;
use QuestionForm\FormManagementModel;
use Mannschaft\MannschaftModel;
use Station\StationModel;

/**
 * Einheitlicher Controller für AJAX-Anfragen im Zusammenhang mit Team-Formular-Zuordnungen
 * Diese Klasse ersetzt die separaten AJAX-Controller, die zuvor verwendet wurden
 */
class TeamFormApiController {
    private PDO $db;
    private TeamFormRelationModel $teamFormModel;
    private FormManagementModel $formModel;
    private MannschaftModel $mannschaftModel;
    private StationModel $stationModel;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->teamFormModel = new TeamFormRelationModel($db);
        $this->formModel = new FormManagementModel($db);
        $this->mannschaftModel = new MannschaftModel($db);
        $this->stationModel = new StationModel($db);
    }

    public function handleRequest() {
        if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof PDO)) {
            $this->sendErrorResponse('Datenbankverbindung nicht verfügbar.');
            return;
        }

        // Anfrage-Parameter prüfen
        $action = $_GET['action'] ?? '';

        header('Content-Type: application/json');

        switch ($action) {
            // Basisinformationen
            case 'get_forms_by_station':
                $this->getFormsByStation();
                break;

            case 'get_team_status':
                $this->getTeamStatus();
                break;

            // Detaillierte Informationen
            case 'get_team_forms':
                $this->getTeamForms();
                break;

            case 'get_form_statistics':
                $this->getFormStatistics();
                break;

            case 'get_all_teams_status':
                $this->getAllTeamsStatus();
                break;

            case 'get_all_forms_status':
                $this->getAllFormsStatus();
                break;

            case 'reset_all_results':
                $this->resetAllResults();
                break;

            default:
                $this->sendErrorResponse('Unbekannte Aktion.');
                break;
        }
    }

    /**
     * Gibt alle Formulare für eine bestimmte Station zurück
     */
    private function getFormsByStation() {
        $stationId = isset($_GET['station_id']) ? intval($_GET['station_id']) : 0;

        if ($stationId <= 0) {
            $this->sendErrorResponse('Ungültige Stations-ID.');
            return;
        }

        try {
            // Formulare für diese Station abrufen
            $stmt = $this->db->prepare(
                "SELECT qf.ID, qf.Titel AS titel, qf.Station_ID, qf.token, s.name AS station_name 
                FROM `QuestionForm` qf 
                JOIN Station s ON qf.Station_ID = s.ID
                WHERE qf.Station_ID = :station_id"
            );
            $stmt->bindParam(':station_id', $stationId, PDO::PARAM_INT);
            $stmt->execute();
            $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->sendSuccessResponse(['forms' => $forms]);
        } catch (PDOException $e) {
            error_log('TeamFormController: ' . $e->getMessage());
            $this->sendErrorResponse('Fehler beim Abrufen der Formulare.');
        }
    }

    /**
     * Gibt den Status eines Teams in Bezug auf alle Formulare zurück
     */
    private function getTeamStatus() {
        $teamId = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

        if ($teamId <= 0) {
            $this->sendErrorResponse('Ungültige Team-ID.');
            return;
        }

        try {
            // Team-Informationen abrufen
            $team = $this->mannschaftModel->read($teamId);
            if (!$team) {
                $this->sendErrorResponse('Team nicht gefunden.');
                return;
            }

            // Formulare für dieses Team abrufen
            $forms = $this->teamFormModel->getFormsByTeam($teamId);

            // Statistiken zusammenfassen
            $totalForms = count($forms);
            $completedForms = 0;
            $totalPoints = 0;

            foreach ($forms as $form) {
                if ($form['completed'] == 1) {
                    $completedForms++;
                    $totalPoints += intval($form['points'] ?? 0);
                }
            }

            $this->sendSuccessResponse([
                'team' => $team,
                'forms_count' => $totalForms,
                'completed_count' => $completedForms,
                'total_points' => $totalPoints,
                'completion_percentage' => ($totalForms > 0) ? round(($completedForms / $totalForms) * 100, 1) : 0
            ]);
        } catch (PDOException $e) {
            error_log('TeamFormController: ' . $e->getMessage());
            $this->sendErrorResponse('Fehler beim Abrufen des Team-Status.');
        }
    }

    /**
     * Gibt detaillierte Informationen zu allen Formularen eines Teams zurück
     */
    private function getTeamForms() {
        $teamId = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

        if ($teamId <= 0) {
            $this->sendErrorResponse('Ungültige Team-ID.');
            return;
        }

        try {
            // Team-Informationen abrufen
            $team = $this->mannschaftModel->read($teamId);
            if (!$team) {
                $this->sendErrorResponse('Team nicht gefunden.');
                return;
            }

            // Formulare für dieses Team abrufen
            $forms = $this->teamFormModel->getFormsByTeam($teamId);

            // Anzahl der Fragen pro Formular abrufen
            foreach ($forms as &$form) {
                $formId = $form['form_ID'];
                $form['question_count'] = $this->formModel->getFormQuestionCount($formId);
            }

            $this->sendSuccessResponse([
                'success' => true,
                'team' => $team,
                'forms' => $forms
            ]);
        } catch (PDOException $e) {
            error_log('TeamFormController: ' . $e->getMessage());
            $this->sendErrorResponse('Fehler beim Abrufen der Team-Formulare.');
        }
    }

    /**
     * Gibt statistische Informationen zu einem Formular zurück
     */
    private function getFormStatistics() {
        $formId = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;

        if ($formId <= 0) {
            $this->sendErrorResponse('Ungültige Formular-ID.');
            return;
        }

        try {
            // Formular-Informationen abrufen
            $form = $this->formModel->read($formId);
            if (!$form) {
                $this->sendErrorResponse('Formular nicht gefunden.');
                return;
            }

            // Statistiken zum Formular abrufen
            $stats = $this->teamFormModel->getTeamStatsByForm($formId);

            // Teams mit diesem Formular abrufen
            $stmt = $this->db->prepare(
                "SELECT tf.*, m.Teamname, m.Kreisverband
                FROM TeamForm tf
                JOIN Mannschaft m ON tf.team_ID = m.ID
                WHERE tf.form_ID = :form_id
                ORDER BY m.Teamname"
            );
            $stmt->bindParam(':form_id', $formId, PDO::PARAM_INT);
            $stmt->execute();
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Anzahl der Fragen im Formular
            $questionCount = $this->formModel->getFormQuestionCount($formId);

            $statistics = [
                'form_id' => $formId,
                'form_title' => $form['Titel'] ?? 'Unbekanntes Formular',
                'station_name' => $form['station_name'] ?? '-',
                'question_count' => $questionCount,
                'total_teams' => $stats['total_count'] ?? 0,
                'completed_count' => $stats['completed_count'] ?? 0,
                'average_points' => $stats['average_points'] ?? 0,
                'teams' => $teams
            ];

            $this->sendSuccessResponse([
                'success' => true,
                'statistics' => $statistics
            ]);
        } catch (PDOException $e) {
            error_log('TeamFormController: ' . $e->getMessage());
            $this->sendErrorResponse('Fehler beim Abrufen der Formular-Statistiken.');
        }
    }

    /**
     * Gibt einen Überblick über den Status aller Teams zurück
     */
    private function getAllTeamsStatus() {
        try {
            // Alle Teams mit ihrer Formular-Statistik abrufen
            $teams = $this->teamFormModel->getAllTeamsWithFormsCount();

            // Gesamtzahl der Formulare
            $totalFormCount = count($this->formModel->read());

            // Erweitern der Team-Daten um Prozentsätze
            foreach ($teams as &$team) {
                $team['completion_percentage'] = $totalFormCount > 0 ?
                    round((intval($team['completed_forms']) / $totalFormCount) * 100, 1) : 0;
            }

            $this->sendSuccessResponse([
                'teams' => $teams,
                'total_form_count' => $totalFormCount
            ]);
        } catch (PDOException $e) {
            error_log('TeamFormController: ' . $e->getMessage());
            $this->sendErrorResponse('Fehler beim Abrufen der Team-Statistiken.');
        }
    }

    /**
     * Gibt einen Überblick über den Status aller Formulare zurück
     */
    private function getAllFormsStatus() {
        try {
            // Alle Formulare abrufen
            $forms = $this->formModel->read();

            // Formulare mit Statistiken anreichern
            foreach ($forms as &$form) {
                $stats = $this->teamFormModel->getTeamStatsByForm($form['ID']);
                $form['total_teams'] = $stats['total_count'] ?? 0;
                $form['completed_count'] = $stats['completed_count'] ?? 0;
                $form['average_points'] = $stats['average_points'] ?? 0;
                $form['question_count'] = $this->formModel->getFormQuestionCount($form['ID']);
            }

            $this->sendSuccessResponse([
                'forms' => $forms
            ]);
        } catch (PDOException $e) {
            error_log('TeamFormController: ' . $e->getMessage());
            $this->sendErrorResponse('Fehler beim Abrufen der Formular-Statistiken.');
        }
    }

    /**
     * Setzt alle Formularergebnisse zurück
     */
    private function resetAllResults() {
        try {
            // Nur mit POST erlauben
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendErrorResponse('Diese Aktion erfordert eine POST-Anfrage.');
                return;
            }

            // Bestätigung erforderlich
            $confirmation = isset($_POST['confirm']) && $_POST['confirm'] === 'true';
            if (!$confirmation) {
                $this->sendErrorResponse('Bestätigung erforderlich für diese Aktion.');
                return;
            }

            $stmt = $this->db->prepare(
                "UPDATE TeamForm SET completed = 0, points = 0, completion_date = NULL"
            );
            $success = $stmt->execute();

            if ($success) {
                $rowCount = $stmt->rowCount();
                $this->sendSuccessResponse([
                    'message' => "Alle Formularergebnisse zurückgesetzt ($rowCount Einträge betroffen)."
                ]);
            } else {
                $this->sendErrorResponse('Fehler beim Zurücksetzen der Ergebnisse.');
            }
        } catch (PDOException $e) {
            error_log('TeamFormController: ' . $e->getMessage());
            $this->sendErrorResponse('Datenbankfehler.');
        }
    }

    /**
     * Hilfsmethode zum Senden einer Fehlerantwort im JSON-Format
     */
    private function sendErrorResponse($message) {
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
    }

    /**
     * Hilfsmethode zum Senden einer Erfolgsantwort im JSON-Format
     */
    private function sendSuccessResponse($data) {
        echo json_encode(array_merge(
            ['success' => true],
            $data
        ));
    }
}

// Automatische Ausführung, wenn die Datei direkt aufgerufen wird
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    $controller = new TeamFormApiController($GLOBALS['conn']);
    $controller->handleRequest();
}