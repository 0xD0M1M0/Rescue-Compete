<?php
namespace FormSubmission;

require_once '../model/MannschaftModel.php';

use PDO;
use PDOException;
use QuestionForm\FormManagementModel;
use Question\QuestionModel;
use Answer\AnswerModel;
use TeamForm\TeamFormRelationModel;
use Mannschaft\MannschaftModel;

class FormSubmissionController {
    private PDO $db;
    private FormManagementModel $formModel;
    private QuestionModel $questionModel;
    private AnswerModel $answerModel;
    private TeamFormRelationModel $teamFormModel;
    private MannschaftModel $mannschaftModel;

    public string $message = '';
    public array $submissionData = [];

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->formModel = new FormManagementModel($db);
        $this->questionModel = new QuestionModel($db);
        $this->answerModel = new AnswerModel($db);
        $this->teamFormModel = new TeamFormRelationModel($db);
        $this->mannschaftModel = new MannschaftModel($db);
    }

    /**
     * Verarbeitet das Absenden eines Formulars
     *
     * @return bool True wenn das Formular erfolgreich abgesendet wurde, sonst false
     */
    public function handleSubmission(): bool {
        // Prüfen, ob ein Formular abgesendet wurde
        if (!isset($_POST['submit_answers'])) {
            return false;
        }

        // Token aus dem GET-Request extrahieren
        $token = isset($_GET['token']) ? trim($_GET['token']) : '';

        // Validieren
        if (empty($token)) {
            $this->message = "Ungültiges Formular.";
            return false;
        }

        // TeamForm anhand des Tokens abrufen
        $teamForm = $this->teamFormModel->getTeamFormByToken($token);
        if (!$teamForm) {
            $this->message = "Formular nicht gefunden.";
            return false;
        }

        $formId = $teamForm['form_ID'];
        $teamId = $teamForm['team_ID'];

        // Prüfen, ob das Formular bereits abgeschlossen wurde
        if ($teamForm['completed'] == 1) {
            $this->message = "Dieses Formular wurde bereits ausgefüllt.";
            $this->submissionData = $teamForm;
            return true;
        }

        // Antworten aus dem POST-Request extrahieren
        $submittedAnswers = $_POST['answers'] ?? [];
        if (empty($submittedAnswers)) {
            $this->message = "Keine Antworten übermittelt.";
            return false;
        }

        try {
            // Transaktion starten
            $this->db->beginTransaction();

            // Formular mit Fragen laden
            $form = $this->getFormDetails($formId);
            if (!$form) {
                throw new PDOException("Formular nicht gefunden.");
            }

            // Anzahl der korrekten Antworten zählen
            $totalPoints = 0;
            foreach ($submittedAnswers as $questionId => $answerId) {
                $answer = $this->answerModel->read($answerId);
                if ($answer && $answer['IsCorrect'] == 1) {
                    $totalPoints++;
                }
            }

            // Gesamtanzahl der Fragen im Formular
            $formQuestions = $form['questions'];
            $totalQuestions = count($formQuestions);

            // Formular als abgeschlossen markieren und Punkte speichern
            $completionDate = date('Y-m-d H:i:s');
            $success = $this->teamFormModel->updateFormCompletion($teamId, $formId, true, $totalPoints, $completionDate);

            if (!$success) {
                throw new PDOException("Fehler beim Speichern der Formularergebnisse.");
            }

            // Transaktion abschließen
            $this->db->commit();

            // Erfolgsmeldung und Zusammenfassung
            $this->message = "Vielen Dank! Ihr Formular wurde erfolgreich abgesendet.";
            $this->submissionData = [
                'team_id' => $teamId,
                'form_id' => $formId,
                'points' => $totalPoints,
                'totalQuestions' => $totalQuestions,
                'completion_date' => $completionDate
            ];

            return true;
        } catch (PDOException $e) {
            // Bei Fehler Transaktion zurückrollen
            $this->db->rollBack();
            error_log("Fehler beim Speichern:" . $e->getMessage());
            $this->message = "Ein Fehler ist aufgetreten.";
            error_log("Error in FormSubmissionController::handleSubmission: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Holt ein FormularDetails anhand seiner ID mit allen zugehörigen Fragen und Antworten
     *
     * @param int $formId ID des Formulars
     * @return array|null Das Formular mit allen Daten oder null wenn nicht gefunden
     */
    private function getFormDetails(int $formId): ?array {
        try {
            // Formular anhand der ID abrufen
            $stmt = $this->db->prepare(
                "SELECT f.*, s.name AS station_name
                 FROM QuestionForm f
                 LEFT JOIN Station s ON f.Station_ID = s.ID
                 WHERE f.ID = :formId"
            );
            $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);
            $stmt->execute();

            $form = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$form) {
                return null;
            }

            // Fragen des Formulars abrufen
            $stmt = $this->db->prepare(
                "SELECT q.* 
                 FROM Question q
                 JOIN FormQuestion fq ON q.ID = fq.question_ID
                 WHERE fq.form_ID = :formId
                 ORDER BY RAND()" // Zufällige Reihenfolge
            );
            $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);
            $stmt->execute();

            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $form['questions'] = [];

            // Für jede Frage die Antworten abrufen
            foreach ($questions as $question) {
                $answers = $this->answerModel->getAnswersByQuestion($question['ID']);

                // Antworten mischen
                shuffle($answers);

                $question['answers'] = $answers;
                $form['questions'][] = $question;
            }

            return $form;
        } catch (PDOException $e) {
            error_log("Error in getFormDetails: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Holt ein Formular anhand des Tokens mit allen zugehörigen Fragen und Antworten
     *
     * @param string $token Das Token des Formulars
     * @return array|null Das Formular mit allen Daten oder null wenn nicht gefunden
     */
    public function getFormByToken(string $token): ?array {
        try {
            // TeamForm anhand des Tokens abrufen
            $teamForm = $this->teamFormModel->getTeamFormByToken($token);
            if (!$teamForm) {
                return null;
            }

            // Formular anhand der ID aus TeamForm abrufen
            $formId = $teamForm['form_ID'];
            $form = $this->getFormDetails($formId);

            if ($form) {
                // TeamForm Daten hinzufügen
                $form['team_ID'] = $teamForm['team_ID'];
                $form['completed'] = $teamForm['completed'];
                $form['points'] = $teamForm['points'];
                $form['completion_date'] = $teamForm['completion_date'];
                $form['token'] = $teamForm['token'];

                // Start-Zeit hinzufügen, falls vorhanden
                if (isset($teamForm['start_time'])) {
                    $form['start_time'] = $teamForm['start_time'];
                }
            }

            return $form;
        } catch (PDOException $e) {
            error_log("Error in getFormByToken: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Holt alle Mannschaften für die Dropdown-Liste
     *
     * @return array Liste aller Mannschaften
     */
    public function getAllTeams(): array {
        try {
            return $this->mannschaftModel->read();
        } catch (PDOException $e) {
            error_log("Error in getAllTeams: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Holt die Mannschaft für ein bestimmtes Formular (basierend auf dem Token)
     *
     * @param string $token Das Token des Formulars
     * @return array|null Die Mannschaftsdaten oder null wenn nicht gefunden
     */
    public function getTeamByToken(string $token): ?array {
        try {
            $teamForm = $this->teamFormModel->getTeamFormByToken($token);
            if (!$teamForm) {
                return null;
            }

            $teamId = $teamForm['team_ID'];
            return $this->mannschaftModel->read($teamId);
        } catch (PDOException $e) {
            error_log("Error in getTeamByToken: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Aktualisiert oder setzt die Timer-Startzeit für ein Formular
     *
     * @param int $teamId ID der Mannschaft
     * @param int $formId ID des Formulars
     * @param string|null $startTime Startzeit (oder null, um aktuelle Zeit zu verwenden)
     * @return bool Erfolg der Operation
     */
    public function updateTimerStartTime(int $teamId, int $formId, string $startTime = null): bool
    {
        try {
            if ($startTime === null) {
                $startTime = date('Y-m-d H:i:s');
            }

            $stmt = $this->db->prepare(
                "UPDATE TeamForm SET start_time = :startTime 
             WHERE team_ID = :teamId AND form_ID = :formId"
            );
            $stmt->bindParam(':startTime', $startTime);
            $stmt->bindParam(':teamId', $teamId, PDO::PARAM_INT);
            $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Fehler beim Aktualisieren der Timer-Startzeit: " . $e->getMessage());
            return false;
        }
    }
}