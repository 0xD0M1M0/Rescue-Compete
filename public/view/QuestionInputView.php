<?php
require_once '../db/DbConnection.php';
require_once '../model/QuizPoolModel.php';
require_once '../model/QuestionModel.php';
require_once '../model/AnswerModel.php';
require_once '../controller/QuestionPoolInputController.php';
require_once '../controller/QuestionInputController.php';
require_once '../php_assets/CustomAlertBox.php';
require_once __DIR__ . '/../php_assets/SecurityHelpers.php';

use QuestionPool\QuizPoolModel;
use Question\QuestionModel;
use Answer\AnswerModel;
use QuestionPool\QuestionPoolInputController;
use Question\QuestionInputController;

require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();

// Weiterleitung, wenn Berechtigungen nicht korrekt sind
$allowedAccountTypes = ['Wettkampfleitung', 'Admin'];
if(!isset($_SESSION["acc_typ"]) || !in_array($_SESSION["acc_typ"], $allowedAccountTypes)){
    header("Location: ../index.php");
    exit;
}

if (!isset($conn)) {
    die("Datenbankverbindung nicht verfügbar.");
}

// Modelle instanziieren
$questionPoolModel = new QuizPoolModel($conn);
$questionModel = new QuestionModel($conn);
$answerModel = new AnswerModel($conn);

// Controller instanziieren
$poolController = new QuestionPoolInputController($questionPoolModel);
$questionController = new QuestionInputController($conn, $questionPoolModel, $questionModel, $answerModel);

// AJAX-Request für Fragen laden
if (isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['action']) && $_GET['action'] == 'load_questions') {
    header('Content-Type: application/json');

    if (isset($_GET['pool_id']) && is_numeric($_GET['pool_id'])) {
        $poolId = intval($_GET['pool_id']);
        $questions = $questionModel->getQuestionsByPool($poolId);

        echo json_encode([
            'success' => true,
            'questions' => $questions
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Ungültige Pool-ID'
        ]);
    }
    exit;
}

// Request verarbeiten
$poolController->handleRequest();
$questionController->handleRequest();

// Daten für die View
$poolMessage = $poolController->message;
$questionMessage = $questionController->message;
$modalData = $questionController->modalData;

// Alle Fragenpools für Dropdowns und Übersicht
$questionPools = $questionPoolModel->read();

// Aktuell ausgewählter Pool für Fragen-Anzeige
$selectedPoolId = isset($_GET['pool_id']) ? intval($_GET['pool_id']) : null;
$questions = $selectedPoolId ? $questionModel->getQuestionsByPool($selectedPoolId) : [];

// Aktuelle Ansicht bestimmen
$currentView = sanitize_view_param($_GET['view'] ?? null, ['pool_overview', 'pool_create', 'question_overview', 'question_create'], 'pool_overview');

// Formularwerte für Pool (ggf. aus vorherigem POST)
$poolName = isset($_POST['name']) ? trim($_POST['name']) : "";

$pageTitle = "Fragen Verwaltung";
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RescueCompete - <?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logos/ww-favicon.ico">
    <link rel="stylesheet" href="../css/Colors.css">
    <link rel="stylesheet" href="../css/GlobalLayout.css">
    <link rel="stylesheet" href="../css/Navbar.css">
    <link rel="stylesheet" href="../css/Sidebar.css">
    <link rel="stylesheet" href="../css/Footer.css">
    <link rel="stylesheet" href="../css/Components.css">
    <link rel="stylesheet" href="../css/QuestionInputStyling.css">
</head>
<body class="has-navbar">
<!-- Navbar -->
<?php include '../php_assets/Navbar.php'; ?>

<div class="container">
    <!-- Sidebar -->
    <?php include '../php_assets/Sidebar.php'; ?>

    <!-- Hauptinhalt -->
    <div class="main-content vertical">

        <!-- Navigation Tabs -->
        <div class="tab-navigation">
            <button class="tab-button <?php echo $currentView === 'pool_overview' ? 'active' : ''; ?>"
                    data-tab="pool_overview"
                    onclick="showTab('pool_overview')">Fragenpool Übersicht</button>
            <button class="tab-button <?php echo $currentView === 'pool_create' ? 'active' : ''; ?>"
                    data-tab="pool_create"
                    onclick="showTab('pool_create')">Fragenpool hinzufügen</button>
            <button class="tab-button <?php echo $currentView === 'question_overview' ? 'active' : ''; ?>"
                    data-tab="question_overview"
                    onclick="showTab('question_overview')">Fragen Übersicht</button>
            <button class="tab-button <?php echo $currentView === 'question_create' ? 'active' : ''; ?>"
                    data-tab="question_create"
                    onclick="showTab('question_create')">Fragen hinzufügen</button>
        </div>

        <!-- Statusmeldungen -->
        <?php if (!empty($poolMessage)): ?>
            <div class="message-box success">
                <?php echo htmlspecialchars($poolMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($questionMessage)): ?>
            <div class="message-box success">
                <?php echo htmlspecialchars($questionMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Tab: Fragenpool Übersicht -->
        <div id="pool_overview" class="tab-content <?php echo $currentView === 'pool_overview' ? 'active' : ''; ?>">
            <div class="data-container">
                <div class="actions-bar">
                    <button class="btn primary-btn" onclick="showTab('pool_create')">
                        Neuen Fragenpool erstellen
                    </button>
                </div>

                <?php if (empty($questionPools)): ?>
                    <div class="no-data">
                        <p>Keine Fragenpools vorhanden.</p>
                        <p><a href="#" onclick="showTab('pool_create')">Erstellen Sie Ihren ersten Fragenpool</a></p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th width="40%">Name</th>
                            <th width="15%">Anzahl Fragen</th>
                            <th width="20%">Erstellt</th>
                            <th width="25%">Aktionen</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($questionPools as $pool): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($pool['Name']); ?></strong>
                                </td>
                                <td class="numeric-cell"><?php echo $questionPoolModel->getQuestionCount($pool['ID']); ?></td>
                                <td><?php echo isset($pool['CreatedAt']) ? date('d.m.Y', strtotime($pool['CreatedAt'])) : '-'; ?></td>
                                <td class="action-cell">
                                    <div class="button-group">
                                        <button class="btn small" onclick="viewPoolQuestions(<?php echo $pool['ID']; ?>)">
                                            Fragen anzeigen
                                        </button>
                                        <button class="btn warning-btn small" onclick="confirmDeletePool(<?php echo (int)$pool['ID']; ?>, <?php echo json_encode_for_js($pool['Name']); ?>)">
                                            Löschen
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Fragenpool hinzufügen -->
        <div id="pool_create" class="tab-content <?php echo $currentView === 'pool_create' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>Neuen Fragenpool hinzufügen</h3>

                <form method="POST" id="createPoolForm">
                    <input type="hidden" name="add_pool" value="1">

                    <div class="form-group">
                        <label for="pool_name">Name des Fragenpools *</label>
                        <input type="text" id="pool_name" name="name" required
                               value="<?php echo htmlspecialchars($poolName); ?>"
                               placeholder="z.B. Erste-Hilfe-Grundlagen">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn primary-btn">Fragenpool erstellen</button>
                        <button type="button" class="btn" onclick="showTab('pool_overview')">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab: Fragen Übersicht -->
        <div id="question_overview" class="tab-content <?php echo $currentView === 'question_overview' ? 'active' : ''; ?>">
            <div class="data-container">
                <div class="collection-selector">
                    <label for="overview_pool_select">Fragenpool auswählen:</label>
                    <select id="overview_pool_select" onchange="loadPoolQuestions()">
                        <option value="">Bitte wählen...</option>
                        <?php foreach ($questionPools as $pool): ?>
                            <option value="<?php echo $pool['ID']; ?>"
                                <?php echo ($selectedPoolId == $pool['ID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pool['Name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="questionsOverviewContainer">
                    <?php if ($selectedPoolId && !empty($questions)): ?>
                        <?php foreach ($questions as $question): ?>
                            <div class="question-container">
                                <div class="question-header">
                                    <h3><?php echo htmlspecialchars($question['Text']); ?></h3>
                                    <form method="POST" class="delete-form">
                                        <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($question['ID']); ?>">
                                        <input type="hidden" name="delete_question" value="1">
                                        <button type="submit" class="btn warning-btn small">Löschen</button>
                                    </form>
                                </div>

                                <!-- Antworten zu dieser Frage -->
                                <?php
                                $answers = $answerModel->getAnswersByQuestion($question['ID']);
                                if (!empty($answers)):
                                    ?>
                                    <table class="answers-table">
                                        <thead>
                                        <tr>
                                            <th width="60%">Antwort</th>
                                            <th width="20%">Korrekt</th>
                                            <th width="20%">Aktion</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($answers as $answer): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($answer['Text']); ?></td>
                                                <td class="numeric-cell">
                                                    <span class="status-badge <?php echo $answer['IsCorrect'] ? 'completed' : 'pending'; ?>">
                                                        <?php echo $answer['IsCorrect'] ? 'Ja' : 'Nein'; ?>
                                                    </span>
                                                </td>
                                                <td class="action-cell">
                                                    <form method="POST" class="delete-form">
                                                        <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['ID']); ?>">
                                                        <input type="hidden" name="delete_answer" value="1">
                                                        <button type="submit" class="btn warning-btn small">Löschen</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="no-data-small">Keine Antworten für diese Frage vorhanden.</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($selectedPoolId && empty($questions)): ?>
                        <div class="no-data">
                            <p>Keine Fragen für diesen Fragenpool vorhanden.</p>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <p>Wählen Sie einen Fragenpool aus, um die Fragen anzuzeigen.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab: Fragen hinzufügen -->
        <div id="question_create" class="tab-content <?php echo $currentView === 'question_create' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>Fragen hinzufügen</h3>

                <form method="POST" id="createQuestionForm">
                    <input type="hidden" name="add_questions" value="1">

                    <div class="form-group">
                        <label for="question_pool_id">Fragenpool *</label>
                        <select id="question_pool_id" name="pool_id" required>
                            <option value="">Bitte wählen...</option>
                            <?php foreach ($questionPools as $pool): ?>
                                <option value="<?php echo $pool['ID']; ?>">
                                    <?php echo htmlspecialchars($pool['Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="questions-container">
                        <!-- Initial wird ein Frage-Block erstellt -->
                        <div class="question-block" data-question-index="0">
                            <div class="question-block-header">
                                <h4>Frage 1</h4>
                            </div>

                            <div class="form-group">
                                <label for="question_text_0">Fragetext * (max. 200 Zeichen)</label>
                                <textarea id="question_text_0" name="questions[0][text]" rows="3" required maxlength="200"
                                          placeholder="Geben Sie hier Ihre Frage ein..."></textarea>
                            </div>

                            <div class="answers-section">
                                <h5>Antworten:</h5>
                                <div class="answer-container-header">
                                    <div class="answer-column">Antworttext</div>
                                    <div class="korrekt-column">Korrekt</div>
                                </div>

                                <div class="answer-container">
                                    <input type="text" name="questions[0][answers][0][text]" class="answer-input" placeholder="Antwort 1" required>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="questions[0][answers][0][is_correct]" value="1">
                                    </label>
                                </div>
                                <div class="answer-container">
                                    <input type="text" name="questions[0][answers][1][text]" class="answer-input" placeholder="Antwort 2" required>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="questions[0][answers][1][is_correct]" value="1">
                                    </label>
                                </div>
                                <div class="answer-container">
                                    <input type="text" name="questions[0][answers][2][text]" class="answer-input" placeholder="Antwort 3" required>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="questions[0][answers][2][is_correct]" value="1">
                                    </label>
                                </div>
                                <div class="answer-container">
                                    <input type="text" name="questions[0][answers][3][text]" class="answer-input" placeholder="Antwort 4" required>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="questions[0][answers][3][is_correct]" value="1">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Buttons für Frage-Management -->
                    <div class="question-management">
                        <div class="button-group">
                            <button type="button" id="add-question-btn" class="btn secondary-btn">Weitere Frage hinzufügen</button>
                            <button type="button" id="remove-question-btn" class="btn secondary-btn">Frage entfernen</button>
                        </div>
                        <div class="questions-info">
                            <span id="questions-count">1 Frage</span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn primary-btn">Fragen hinzufügen</button>
                        <button type="button" class="btn" onclick="showTab('question_overview')">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<?php
// Fragenpool-Löschung bestätigen
echo CustomAlertBox::renderSimpleConfirm(
    "confirmDeletePoolModal",
    "Fragenpool löschen",
    "Möchten Sie diesen Fragenpool und alle zugehörigen Fragen wirklich löschen?",
    "deletePool()",
    "closeModal('confirmDeletePoolModal')"
);

// Frage-Löschung bestätigen
echo CustomAlertBox::renderSimpleConfirm(
    "confirmDeleteQuestionModal",
    "Frage löschen",
    "Möchten Sie diese Frage wirklich löschen? Alle zugehörigen Antworten werden ebenfalls gelöscht.",
    "deleteQuestion()",
    "closeModal('confirmDeleteQuestionModal')"
);

// Antwort-Löschung bestätigen
echo CustomAlertBox::renderSimpleConfirm(
    "confirmDeleteAnswerModal",
    "Antwort löschen",
    "Möchten Sie diese Antwort wirklich löschen?",
    "deleteAnswer()",
    "closeModal('confirmDeleteAnswerModal')"
);

// Erfolgs-/Fehlermeldungen
if (!empty($poolMessage) || !empty($questionMessage)):
    $message = !empty($poolMessage) ? $poolMessage : $questionMessage;
    $alertType = (strpos($message, 'erfolgreich') !== false) ? 'Erfolg' : 'Hinweis';
    echo CustomAlertBox::renderSimpleAlert(
        "messageAlert",
        $alertType,
        $message
    );
    echo '<script>document.getElementById("messageAlert").classList.add("active");</script>';
endif;
?>

<!-- JavaScript einbinden -->
<script src="../js/ModalUtils.js"></script>
<script src="../js/QuestionInputScript.js"></script>

<!-- Tab-Initialisierung sicherstellen -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sicherstellen, dass der korrekte Tab angezeigt wird
        const currentView = <?php echo json_encode_for_js($currentView); ?>;
        showTab(currentView);
    });
</script>

<?php include '../php_assets/Footer.php'; ?>

</body>
</html>