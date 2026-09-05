<?php
namespace QuestionForm;

require_once '../db/DbConnection.php';
require_once '../model/QuestionModel.php';
require_once '../model/FormManagementModel.php';
require_once '../model/MannschaftModel.php';
require_once '../model/TeamFormRelationModel.php';

use Exception;
use PDO;
use Question\QuestionModel;
use Mannschaft\MannschaftModel;
use TeamForm\TeamFormRelationModel;

class QuestionFormInputController
{
    private FormManagementModel $formModel;
    private QuestionModel $questionModel;
    private MannschaftModel $teamModel;
    private TeamFormRelationModel $teamFormModel;
    public string $message = "";
    public array $modalData = [];
    public string $redirectUrl = "QuestionFormInputView.php";

    public function __construct(
        FormManagementModel $formModel,
        QuestionModel $questionModel,
        MannschaftModel $teamModel,
        TeamFormRelationModel $teamFormModel
    ) {
        $this->formModel = $formModel;
        $this->questionModel = $questionModel;
        $this->teamModel = $teamModel;
        $this->teamFormModel = $teamFormModel;

        // Sicherstellen, dass die DB-Verbindung verfügbar ist
        if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof PDO)) {
            die(json_encode(['success' => false, 'message' => 'Datenbankverbindung nicht verfügbar.']));
        }
    }

    /**
     * Behandelt reguläre Anfragen (POST/GET) für Formularaktionen
     */
    public function handleRequest() {

        // Löschen eines Frageformulars (oder mehrerer mit gleichem Titel)
        if (isset($_POST['delete_questionform'])) {
            $deleteId = intval($_POST['delete_ID']);

            // Formular laden, um den Titel zu ermitteln
            $formToDelete = $this->formModel->read($deleteId);

            if ($formToDelete) {
                $title = $formToDelete['Titel'] ?? $formToDelete['titel'] ?? '';

                // Alle Formulare mit diesem Titel löschen
                $deletedCount = $this->deleteFormsByTitle($title);

                if ($deletedCount > 0) {
                    $this->message = $deletedCount > 1
                        ? "Es wurden $deletedCount Formulare mit dem Titel \"$title\" gelöscht."
                        : "Das Formular wurde erfolgreich gelöscht.";
                    header("Location: " . $this->redirectUrl);
                    exit;
                } else {
                    $this->message = "Fehler beim Löschen der Formulare.";
                }
            } else {
                $this->message = "Formular nicht gefunden.";
            }
        }

        // Hinzufügen oder Aktualisieren eines Frageformulars
        if (isset($_POST['create_form'])) {

            // Station prüfen
            $stationId = isset($_POST['station']) ? intval($_POST['station']) : 0;
            if ($stationId <= 0) {
                $this->message = "Bitte wählen Sie eine gültige Station aus.";
                return;
            }

            // Titel prüfen
            $baseTitle = trim($_POST['form_title'] ?? '');
            if (empty($baseTitle)) {
                $this->message = "Der Titel darf nicht leer sein.";
                return;
            }

            // Fragenpool prüfen
            $poolId = isset($_POST['question_pool']) ? intval($_POST['question_pool']) : 0;
            if ($poolId <= 0) {
                $this->message = "Bitte wählen Sie einen gültigen Fragenpool aus.";
                return;
            }

            // Ausgewählte Fragen ermitteln
            $selectedQuestionIds = [];
            if (isset($_POST['selected_questions']) && is_array($_POST['selected_questions'])) {
                $selectedQuestionIds = array_map('intval', $_POST['selected_questions']);
            }

            if (empty($selectedQuestionIds)) {
                $this->message = "Bitte wählen Sie mindestens eine Frage aus.";
                return;
            }

            // Formularanzahl ermitteln
            $formCount = isset($_POST['form_count']) ? max(1, intval($_POST['form_count'])) : 1;

            // Zufällige Verteilung?
            $randomizeOrder = isset($_POST['randomize_order']) && $_POST['randomize_order'] === '1';

            // Formulare erstellen
            $result = $this->createForms(
                $stationId,
                $baseTitle,
                $selectedQuestionIds,
                $formCount,
                $randomizeOrder
            );

            if ($result['status'] === 'success') {
                $this->message = $result['message'];
                header("Location: " . $this->redirectUrl . "?status=success&forms_created=" . $result['forms_created']);
                exit;
            } else {
                $this->message = $result['message'];
            }
        }
    }

    /**
     * Behandelt AJAX-Anfragen über einen Action-Parameter
     */
    public function handleAction(): void {
        $action = $_GET['action'] ?? '';
        header('Content-Type: application/json');

        switch ($action) {
            // Fragen verwalten
            case 'getQuestions':
                $questions = $this->questionModel->getAllQuestions();
                $formatted = array_map(fn($q) => ['id' => (int)$q['ID'], 'text' => $q['Text']], $questions);
                echo json_encode(['success' => true, 'questions' => $formatted]);
                break;

            case 'getQuestionsByPool':
                $poolId = (int)($_GET['poolId'] ?? 0);
                if ($poolId <= 0) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Ungültige Fragenpool-ID.'
                    ]);
                    exit;
                }

                $questions = $this->questionModel->getQuestionsByPool($poolId);
                echo json_encode([
                    'success' => true,
                    'questions' => $questions
                ]);
                break;

            case 'getQuestion':
                $id = (int)($_GET['id'] ?? 0);
                $q = $this->questionModel->read($id);
                if ($q) {
                    echo json_encode(['success' => true, 'question' => $q]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Frage nicht gefunden.']);
                }
                break;

            case 'getQuestionByText':
                $text = $_GET['text'] ?? '';
                $q = $this->questionModel->getQuestionByText($text);
                if ($q) {
                    echo json_encode(['success' => true, 'question' => $q]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Frage nicht gefunden.']);
                }
                break;

            case 'createQuestion':
                $data = $_POST;
                try {
                    $newId = $this->questionModel->create($data);
                    echo json_encode(['success' => true, 'id' => $newId]);
                } catch (Exception $e) {
                    error_log($e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Ein Fehler ist aufgetreten.']);
                }
                break;

            case 'updateQuestion':
                $id = (int)($_POST['id'] ?? 0);
                $data = $_POST;
                if ($this->questionModel->update($id, $data)) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Aktualisierung fehlgeschlagen.']);
                }
                break;

            case 'deleteQuestion':
                $id = (int)($_POST['id'] ?? 0);
                if ($this->questionModel->delete($id)) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Löschen fehlgeschlagen.']);
                }
                break;

            // weitere Aktionen...

            default:
                echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion.']);
                break;
        }
    }

    /**
     * Erstellt mehrere Formulare und verteilt die Fragen auf sie
     *
     * @param int $stationId ID der Station
     * @param string $baseTitle Basistitel der Formulare
     * @param array $questionIds IDs der zu verwendenden Fragen
     * @param int $formCount Anzahl der zu erstellenden Formulare
     * @param bool $randomizeOrder Sollen die Fragen zufällig verteilt werden?
     * @return array Status und Ergebnisse
     */
    private function createForms(int $stationId, string $baseTitle, array $questionIds, int $formCount, bool $randomizeOrder): array {

        try {
            // Entferne Duplikate aus den Fragen-IDs
            $questionIds = array_unique($questionIds);
            $questionCount = count($questionIds);

            if ($questionCount < $formCount) {
                return [
                    'status' => 'error',
                    'message' => "Nicht genug Fragen ausgewählt. Mindestens $formCount Fragen werden benötigt."
                ];
            }

            $teamList = $this->teamModel->getAllMannschaften();
            $createdForms = 0;
            $teamCount = count($teamList);

            // Berechne, wie viele Fragen pro Formular zugewiesen werden sollen
            $questionsPerForm = [];
            $baseQuestionsPerForm = floor($questionCount / $formCount);
            $extraQuestions = $questionCount % $formCount;

            // Verteile die Fragen gleichmäßig auf die Formulare
            for ($j = 0; $j < $formCount; $j++) {
                // Formulare mit Index < extraQuestions bekommen eine zusätzliche Frage
                $questionsPerForm[$j] = $baseQuestionsPerForm + ($j < $extraQuestions ? 1 : 0);
            }

            // für jedes Team
            for ($i = 0; $i < $teamCount; $i++) {
                $teamId = $teamList[$i]['ID'];
                $teamName = $teamList[$i]['Teamname'];

                // Kopie der Fragen-IDs für dieses Team erstellen
                $currentQuestionIds = $questionIds;

                // Fragen zufällig mischen, wenn nötig
                if ($randomizeOrder) {
                    shuffle($currentQuestionIds);
                }

                $questionOffset = 0;

                // Anzahl der Formulare erstellen
                for ($j = 0; $j < $formCount; $j++) {
                    $sequence = $j + 1; // Reihenfolge der Formulare für dieses Team
                    $title = $formCount > 1 ? "$baseTitle (" . ($j + 1) . "/" . $formCount . ")" : $baseTitle;

                    // Formular erstellen
                    $formData = [
                        'titel' => $title,
                        'station_id' => $stationId
                    ];

                    $formId = $this->formModel->create($formData);

                    if (!$formId) {
                        throw new \Exception("Fehler beim Erstellen des Formulars für Team " . $teamName);
                    }

                    // Formular mit Team verknüpfen und Token generieren
                    if (!$this->teamFormModel->assignFormToTeamWithToken($teamId, $formId, $sequence)) {
                        throw new \Exception("Fehler beim Verknüpfen des Formulars mit Team " . $teamName);
                    }

                    // Die für dieses Formular bestimmten Fragen auswählen
                    $formQuestionCount = $questionsPerForm[$j];

                    // Sicherstellen, dass wir nicht über das Array hinausgehen
                    if ($questionOffset + $formQuestionCount > count($currentQuestionIds)) {
                        $formQuestionCount = count($currentQuestionIds) - $questionOffset;
                    }

                    $formQuestions = array_slice($currentQuestionIds, $questionOffset, $formQuestionCount);
                    $questionOffset += $formQuestionCount;

                    // Jede Frage zu dem Formular hinzufügen
                    foreach ($formQuestions as $questionId) {
                        if (!$this->formModel->addQuestionToForm($questionId, $formId)) {
                            throw new \Exception("Fehler beim Hinzufügen von Frage $questionId zu Formular $formId");
                        }
                    }

                    $createdForms++;
                }
            }

            return [
                'status' => 'success',
                'message' => "Es wurden erfolgreich $createdForms Formulare für $teamCount Teams erstellt.",
                'forms_created' => $createdForms,
                'team_count' => $teamCount
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Erstellen der Formulare.'
            ];
        }
    }

    /**
     * Löscht alle Formulare mit einem bestimmten Titel
     *
     * @param string $title Der Titel der zu löschenden Formulare
     * @return int Anzahl der gelöschten Formulare
     */
    private function deleteFormsByTitle(string $title): int {
        try {
            // Alle Formulare mit diesem Titel abrufen
            $forms = $this->formModel->getFormsByTitle($title);

            if (empty($forms)) {
                return 0;
            }

            $deletedCount = 0;

            // Jedes Formular einzeln löschen
            foreach ($forms as $form) {
                $formId = $form['ID'];
                if ($this->formModel->delete($formId)) {
                    $deletedCount++;
                }
            }

            return $deletedCount;
        } catch (\Exception $e) {
            error_log("Error in deleteFormsByTitle: " . $e->getMessage());
            return 0;
        }
    }
}