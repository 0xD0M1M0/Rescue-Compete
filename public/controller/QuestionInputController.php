<?php
namespace Question;

use Exception;
use PDO;
use PDOException;
use QuestionPool\QuizPoolModel;
use Answer\AnswerModel;

class QuestionInputController {
    private PDO $db;
    private QuizPoolModel $questionPoolModel;
    private QuestionModel $questionModel;
    private AnswerModel $answerModel;
    public string $message = "";
    public array $modalData = [];

    public function __construct(PDO $db, QuizPoolModel $questionPoolModel, QuestionModel $questionModel, AnswerModel $answerModel) {
        $this->db = $db;
        $this->questionPoolModel = $questionPoolModel;
        $this->questionModel = $questionModel;
        $this->answerModel = $answerModel;
    }

    public function handleRequest() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
            enforce_post_same_origin();
        }

        // Fragen hinzufügen (einzeln oder mehrere)
        if (isset($_POST['add_questions'])) {
            $this->handleAddQuestions();
        }

        // Legacy: Einzelne Frage hinzufügen
        if (isset($_POST['add_question'])) {
            $this->handleAddSingleQuestion();
        }

        // Frage löschen
        if (isset($_POST['delete_question'])) {
            $this->handleDeleteQuestion();
        }

        // Antwort löschen
        if (isset($_POST['delete_answer'])) {
            $this->handleDeleteAnswer();
        }
    }

    private function handleAddQuestions() {
        try {
            // Beginn der Transaktion
            $this->db->beginTransaction();

            $poolId = intval($_POST['pool_id']);
            $questions = $_POST['questions'] ?? [];

            if (empty($questions)) {
                $this->message = "Keine Fragen übertragen.";
                $this->db->rollBack();
                return;
            }

            $createdQuestions = 0;
            $duplicateQuestions = 0;
            $errors = [];

            foreach ($questions as $index => $questionData) {
                $questionText = trim($questionData['text'] ?? '');
                $answers = $questionData['answers'] ?? [];

                // Fragetext validieren
                if (empty($questionText)) {
                    $errors[] = "Frage " . ($index + 1) . ": Fragetext darf nicht leer sein.";
                    continue;
                }

                if (strlen($questionText) > 200) {
                    $errors[] = "Frage " . ($index + 1) . ": Fragetext darf maximal 200 Zeichen haben.";
                    continue;
                }

                // Prüfen, ob die Frage bereits existiert
                $existingQuestion = $this->questionModel->getQuestionByText($questionText);
                if ($existingQuestion) {
                    $duplicateQuestions++;
                    continue;
                }

                // Antworten validieren
                $validAnswers = [];
                $correctAnswerFound = false;

                foreach ($answers as $answerData) {
                    $answerText = trim($answerData['text'] ?? '');
                    if (!empty($answerText)) {
                        $isCorrect = isset($answerData['is_correct']) ? 1 : 0;
                        if ($isCorrect) {
                            $correctAnswerFound = true;
                        }
                        $validAnswers[] = [
                            'text' => $answerText,
                            'is_correct' => $isCorrect
                        ];
                    }
                }

                if (count($validAnswers) < 2) {
                    $errors[] = "Frage " . ($index + 1) . ": Mindestens zwei Antworten erforderlich.";
                    continue;
                }

                if (!$correctAnswerFound) {
                    $errors[] = "Frage " . ($index + 1) . ": Mindestens eine Antwort muss als korrekt markiert sein.";
                    continue;
                }

                // Frage erstellen
                $questionEntry = [
                    'pool_id' => $poolId,
                    'text' => $questionText
                ];

                $questionId = $this->questionModel->create($questionEntry);

                if (!$questionId) {
                    $errors[] = "Frage " . ($index + 1) . ": Fehler beim Erstellen der Frage.";
                    continue;
                }

                // Antworten erstellen
                foreach ($validAnswers as $answerData) {
                    $answerEntry = [
                        'question_id' => $questionId,
                        'text' => $answerData['text'],
                        'is_correct' => $answerData['is_correct']
                    ];

                    $answerId = $this->answerModel->create($answerEntry);
                    if (!$answerId) {
                        $errors[] = "Frage " . ($index + 1) . ": Fehler beim Erstellen einer Antwort.";
                        continue 2; // Springe zur nächsten Frage
                    }
                }

                $createdQuestions++;
            }

            // Fehler prüfen
            if (!empty($errors)) {
                $this->db->rollBack();
                $this->message = "Fehler beim Erstellen der Fragen: " . implode(', ', $errors);
                return;
            }

            // Erfolgsmeldung bei 0 erstellten Fragen aber ohne Fehler
            if ($createdQuestions === 0 && $duplicateQuestions > 0) {
                $this->db->rollBack();
                $this->message = "Alle {$duplicateQuestions} Fragen existieren bereits.";
                return;
            }

            // Alles erfolgreich - Transaktion abschließen
            $this->db->commit();

            // Erfolgsmeldung zusammenstellen
            $message = "{$createdQuestions} " . ($createdQuestions === 1 ? 'Frage' : 'Fragen') . " erfolgreich hinzugefügt";
            if ($duplicateQuestions > 0) {
                $message .= " ({$duplicateQuestions} Duplikate übersprungen)";
            }
            $this->message = $message . ".";

            // Umleitung zur Fragen-Übersicht
            header("Location: QuestionInputView.php?view=question_overview&pool_id={$poolId}");
            exit;

        } catch (Exception $e) {
            // Rollback bei Fehler
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->message = "Ein Fehler ist aufgetreten.";
            error_log("QuestionInputController::handleAddQuestions: " . $e->getMessage());
        }
    }

    private function handleAddSingleQuestion() {
        try {
            // Beginn der Transaktion
            $this->db->beginTransaction();

            $poolId = intval($_POST['pool_id']);
            $questionText = trim($_POST['question_text']);

            if (empty($questionText)) {
                $this->message = "Fragetext darf nicht leer sein.";
                $this->db->rollBack();
                return;
            }

            if (strlen($questionText) > 200) {
                $this->message = "Fragetext darf maximal 200 Zeichen haben.";
                $this->db->rollBack();
                return;
            }

            // Prüfen, ob die Frage bereits existiert (um Duplikate zu vermeiden)
            $existingQuestion = $this->questionModel->getQuestionByText($questionText);
            if ($existingQuestion) {
                $this->message = "Eine Frage mit diesem Text existiert bereits.";
                $this->db->rollBack();
                return;
            }

            // Neue Frage erstellen
            $questionEntry = [
                'pool_id' => $poolId,
                'text' => $questionText
            ];

            $questionId = $this->questionModel->create($questionEntry);

            if (!$questionId) {
                throw new Exception("Fehler beim Erstellen der Frage.");
            }

            // Antworten zur Frage hinzufügen
            if (isset($_POST['answers']) && is_array($_POST['answers'])) {
                $correctAnswerFound = false;

                foreach ($_POST['answers'] as $answerData) {
                    if (!empty($answerData['text'])) {
                        $answerText = trim($answerData['text']);
                        $isCorrect = isset($answerData['is_correct']) ? 1 : 0;

                        if ($isCorrect) {
                            $correctAnswerFound = true;
                        }

                        $answerEntry = [
                            'question_id' => $questionId,
                            'text' => $answerText,
                            'is_correct' => $isCorrect
                        ];

                        $answerId = $this->answerModel->create($answerEntry);
                        if (!$answerId) {
                            throw new Exception("Fehler beim Erstellen einer Antwort.");
                        }
                    }
                }

                // Sicherstellen, dass mindestens eine Antwort als korrekt markiert wurde
                if (!$correctAnswerFound) {
                    throw new Exception("Mindestens eine Antwort muss als korrekt markiert sein.");
                }
            } else {
                throw new Exception("Bitte geben Sie mindestens zwei Antworten an.");
            }

            // Alles erfolgreich - Transaktion abschließen
            $this->db->commit();
            $this->message = "Frage erfolgreich hinzugefügt.";

            // Umleitung zur selben Seite mit dem ausgewählten Pool
            header("Location: QuestionInputView.php?view=question_overview&pool_id={$poolId}");
            exit;

        } catch (Exception $e) {
            // Rollback bei Fehler
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->message = "Ein Fehler ist aufgetreten.";
            error_log("QuestionInputController::handleAddSingleQuestion: " . $e->getMessage());
        }
    }

    private function handleDeleteQuestion() {
        try {
            if (!isset($_POST['question_id'])) {
                $this->message = "Keine Frage-ID übertragen.";
                return;
            }

            $questionId = intval($_POST['question_id']);

            if ($questionId <= 0) {
                $this->message = "Ungültige Frage-ID.";
                return;
            }

            // Hole Pool-ID für die Umleitung
            $question = $this->questionModel->read($questionId);
            $poolId = $question ? $question['QuestionPool_ID'] : null;

            // Lösche die Frage (mit allen Abhängigkeiten durch das Model)
            $result = $this->questionModel->delete($questionId);

            if ($result) {
                $this->message = "Frage und zugehörige Antworten erfolgreich gelöscht.";

                // Umleitung zur Fragen-Übersicht
                if ($poolId) {
                    header("Location: QuestionInputView.php?view=question_overview&pool_id={$poolId}");
                    exit;
                }
            } else {
                $this->message = "Fehler beim Löschen der Frage.";
            }
        } catch (PDOException $e) {
            $this->message = "Ein Fehler ist aufgetreten.";
            error_log("QuestionInputController::handleDeleteQuestion: " . $e->getMessage());
        }
    }

    private function handleDeleteAnswer() {
        try {
            if (!isset($_POST['answer_id'])) {
                $this->message = "Keine Antwort-ID übertragen.";
                return;
            }

            $answerId = intval($_POST['answer_id']);

            if ($answerId <= 0) {
                $this->message = "Ungültige Antwort-ID.";
                return;
            }

            // Beziehe die question_id und zähle die Antworten für diese Frage
            $answer = $this->answerModel->read($answerId);
            if (!$answer) {
                $this->message = "Antwort nicht gefunden.";
                return;
            }

            $questionId = $answer['Question_ID'];
            $answers = $this->answerModel->getAnswersByQuestion($questionId);

            // Verhindern, dass die letzte Antwort einer Frage gelöscht wird
            if (count($answers) <= 2) {
                $this->message = "Eine Frage muss mindestens zwei Antworten haben. Löschen Sie stattdessen die gesamte Frage.";
                return;
            }

            // Verhindere, dass die letzte korrekte Antwort gelöscht wird
            $countCorrectAnswers = $this->answerModel->countCorrectAnswers($questionId);
            if ($countCorrectAnswers <= 1 && $answer['IsCorrect'] == 1) {
                $this->message = "Die letzte korrekte Antwort kann nicht gelöscht werden.";
                return;
            }

            // Hole Pool-ID für die Umleitung
            $question = $this->questionModel->read($questionId);
            $poolId = $question ? $question['QuestionPool_ID'] : null;

            $result = $this->answerModel->delete($answerId);
            if ($result) {
                $this->message = "Antwort erfolgreich gelöscht.";

                // Umleitung zur Fragen-Übersicht
                if ($poolId) {
                    header("Location: QuestionInputView.php?view=question_overview&pool_id={$poolId}");
                    exit;
                }
            } else {
                $this->message = "Fehler beim Löschen der Antwort.";
            }
        } catch (PDOException $e) {
            $this->message = "Ein Fehler ist aufgetreten.";
            error_log("QuestionInputController::handleDeleteAnswer: " . $e->getMessage());
        }
    }
}