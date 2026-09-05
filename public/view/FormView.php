<?php
// FormView.php - Überarbeitet für TeamFormInstance-System
require_once '../db/DbConnection.php';
require_once '../model/FormCollectionModel.php';
require_once '../model/TeamFormInstanceModel.php';
require_once '../model/QuestionModel.php';
require_once '../model/AnswerModel.php';
require_once '../model/TeamModel.php';
require_once '../php_assets/CustomAlertBox.php';
require_once __DIR__ . '/../php_assets/SecurityHelpers.php';

use FormCollection\FormCollectionModel;
use FormCollection\TeamFormInstanceModel;
use Question\QuestionModel;
use Answer\AnswerModel;
use Mannschaft\TeamModel;

require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();

// Parameter verarbeiten
$expired = isset($_GET['expired']) && $_GET['expired'] === '1';
$completed = isset($_GET['completed']) && $_GET['completed'] === '1';
$instanceToken = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($instanceToken)) {
    die("Kein gültiges Formular-Token angegeben.");
}

// Datenbankverbindung prüfen
if (!isset($conn)) {
    die("Datenbankverbindung nicht verfügbar.");
}

// Models instanziieren
$formCollectionModel = new FormCollectionModel($conn);
$teamFormInstanceModel = new TeamFormInstanceModel($conn);
$questionModel = new QuestionModel($conn);
$answerModel = new AnswerModel($conn);
$mannschaftModel = new TeamModel($conn);

// Instance anhand Token laden
$instance = $teamFormInstanceModel->getInstanceByToken($instanceToken);
if (!$instance) {
    die("Formular-Instance nicht gefunden oder ungültiger Token.");
}

$instanceId = $instance['ID'];
$teamId = $instance['team_ID'];
$collectionId = $instance['collection_ID'];
$formNumber = $instance['formNumber'];
$timeLimit = $instance['timeLimit'] ?? 180;
$formSubmitted = $expired || $completed || ($instance['completed'] == 1);

// POST-Request verarbeiten (Formular-Submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answers']) && !$formSubmitted) {
    try {
        $conn->beginTransaction();

        // Timer starten falls noch nicht gestartet
        if (empty($instance['startTime'])) {
            $teamFormInstanceModel->startTimer($instanceId);
        }

        // Antworten speichern
        if (isset($_POST['answers']) && is_array($_POST['answers'])) {
            foreach ($_POST['answers'] as $questionId => $answerId) {
                $teamFormInstanceModel->saveAnswer($instanceId, intval($questionId), intval($answerId));
            }
        }

        // Instance abschließen
        $teamFormInstanceModel->completeInstance($instanceId);

        $conn->commit();

        // Weiterleitung mit completed-Parameter
        header('Location: FormView.php?token=' . urlencode($instanceToken) . '&completed=1');
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error submitting form: " . $e->getMessage());
        $message = "Fehler beim Speichern des Formulars.";
    }
}

// Zugewiesene Fragen laden
$assignedQuestions = $teamFormInstanceModel->getAssignedQuestions($instanceId);
$questionsWithAnswers = [];

foreach ($assignedQuestions as $question) {
    $answers = $answerModel->getAnswersByQuestion($question['ID']);
    shuffle($answers);
    $question['answers'] = $answers;
    $questionsWithAnswers[] = $question;
}

// Bereits gespeicherte Antworten laden
$savedAnswers = [];
if (!$formSubmitted) {
    $instanceAnswers = $teamFormInstanceModel->getAnswersByInstance($instanceId);
    foreach ($instanceAnswers as $answer) {
        $savedAnswers[$answer['question_ID']] = $answer['answer_ID'];
    }
}

// Team-Informationen
$team = $mannschaftModel->read($teamId);
$teamName = $team['Teamname'] ?? 'Unbekannt';
$teamKreisverband = $team['Kreisverband'] ?? '';

// Station-Information
$stationName = $instance['stationName'] ?? 'Unbekannt';

// Seitentitel
$pageTitle = $instance['collectionName'] ?? "Formular";

// Timer starten wenn angefordert
if (!$formSubmitted && isset($_GET['timer_started']) && $_GET['timer_started'] === '1' && empty($instance['startTime'])) {
    $teamFormInstanceModel->startTimer($instanceId);
    // Instance neu laden um aktuelle startTime zu erhalten
    $instance = $teamFormInstanceModel->getInstanceByToken($instanceToken);
}

// Timer-Parameter (nur wenn nicht abgeschlossen)
$timerStarted = !empty($instance['startTime']);
$hasServerEndTime = false;
$serverEndTime = null;

if (!$formSubmitted && $timerStarted) {
    try {
        $startTime = new DateTime($instance['startTime']);
        $endTime = clone $startTime;
        $endTime->add(new DateInterval('PT' . $timeLimit . 'S'));
        $serverEndTime = $endTime->format('Y-m-d H:i:s');
        $hasServerEndTime = true;
    } catch (Exception $e) {
        error_log("Error calculating server end time: " . $e->getMessage());
    }
}

// Message für abgeschlossene Formulare
$message = '';
$submissionData = [];

if ($formSubmitted) {
    if ($expired) {
        $message = "Die Zeit ist abgelaufen. Das Formular wurde automatisch mit den bisher gegebenen Antworten abgesendet.";
    } elseif ($completed) {
        $message = "Formular erfolgreich abgeschlossen.";
    } else {
        $message = "Dieses Formular wurde bereits abgeschlossen.";
    }

    $submissionData = [
        'points' => $instance['points'],
        'totalQuestions' => count($assignedQuestions),
        'completion_date' => $instance['completionDate']
    ];
}
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
    <link rel="stylesheet" href="../css/Footer.css">
    <link rel="stylesheet" href="../css/Components.css">
    <link rel="stylesheet" href="../css/FormViewStyling.css">
    <link rel="manifest" href="../json/WebAppManifest.json">
    <script src="../js/ModalUtils.js"></script>
</head>
<body class="has-navbar">

<div class="form-container">
    <?php if ($formSubmitted): ?>
        <!-- Formular abgeschlossen -->
        <div class="form-success">
            <h2>Formular abgeschlossen</h2>
            <p><?php echo htmlspecialchars($message); ?></p>

            <div class="result-summary">
                <p>Vielen Dank für die Teilnahme! Die Ergebnisse werden von der Wettkampfleitung ausgewertet.</p>
            </div>
        </div>

    <?php elseif (!$timerStarted): ?>
        <!-- Bereitschafts-Anzeige -->
        <div class="ready-overlay">
            <div class="ready-content">
                <h2>Bereit zum Starten?</h2>

                <div class="form-preview">
                    <h3><?php echo htmlspecialchars($pageTitle); ?></h3>
                    <p class="station-info">Station: <?php echo htmlspecialchars($stationName); ?></p>

                    <div class="team-info">
                        <h3>Formular für: <?php echo htmlspecialchars($teamName); ?></h3>
                        <p>Kreisverband: <?php echo htmlspecialchars($teamKreisverband); ?></p>
                    </div>

                    <div class="timer-info">
                        <strong>Zeitlimit:</strong> <?php echo floor($timeLimit / 60); ?> Minuten <?php echo $timeLimit % 60; ?> Sekunden
                    </div>

                    <div class="question-info">
                        <strong>Fragen:</strong> <?php echo count($questionsWithAnswers); ?> Fragen zu beantworten
                    </div>
                </div>

                <div class="instructions">
                    <h4>Hinweise:</h4>
                    <ul>
                        <li>Sobald Sie auf "Bereit! Timer starten" klicken, beginnt die Zeit zu laufen.</li>
                        <li>Der Timer läuft auch weiter, wenn Sie den Browser schließen oder die Seite neu laden.</li>
                        <li>Nach Ablauf der Zeit wird das Formular automatisch mit den bisherigen Antworten abgesendet.</li>
                        <li>Jede Frage muss mit genau einer Antwort beantwortet werden.</li>
                    </ul>
                </div>

                <div class="ready-actions">
                    <a href="FormView.php?token=<?php echo htmlspecialchars($instanceToken); ?>&timer_started=1"
                       class="btn primary">
                        Bereit! Timer starten
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Formular mit Timer -->
        <div class="timer-container" id="timer-container">
            <div class="timer-icon">⏱️</div>
            <div id="timer">Verbleibende Zeit: <?php echo gmdate("i:s", $timeLimit); ?></div>
            <div class="progress-container">
                <div id="progress-bar" class="progress-bar"></div>
            </div>
        </div>

        <div class="form-header">
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p class="station-info">Station: <?php echo htmlspecialchars($stationName); ?></p>

            <div class="team-info">
                <h3>Formular für Mannschaft: <?php echo htmlspecialchars($teamName); ?></h3>
                <p>Kreisverband: <?php echo htmlspecialchars($teamKreisverband); ?></p>
            </div>

            <div class="progress-status">
                <span id="progressStatus">0 von <?php echo count($questionsWithAnswers); ?> Fragen beantwortet</span>
                <div class="progress-bar-container">
                    <div id="progressIndicator" class="progress-indicator"></div>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message-box"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" action="FormView.php?token=<?php echo htmlspecialchars($instanceToken); ?>&timer_started=1" id="question-form">
            <input type="hidden" name="instance_id" value="<?php echo $instanceId; ?>">

            <?php if (empty($questionsWithAnswers)): ?>
                <p class="no-questions">Diesem Formular sind keine Fragen zugeordnet.</p>
            <?php else: ?>
                <div class="questions-container">
                    <?php foreach ($questionsWithAnswers as $index => $question): ?>
                        <div class="question-box <?php echo isset($savedAnswers[$question['ID']]) ? 'answered' : ''; ?>"
                             id="question-box-<?php echo $index; ?>">
                            <h3>Frage <?php echo $index + 1; ?>:</h3>
                            <p class="question-text"><?php echo htmlspecialchars($question['Text']); ?></p>

                            <div class="answers-list">
                                <?php if (empty($question['answers'])): ?>
                                    <p class="no-answers">Keine Antwortoptionen vorhanden.</p>
                                <?php else: ?>
                                    <?php foreach ($question['answers'] as $answer): ?>
                                        <div class="answer-option">
                                            <input type="radio"
                                                   id="answer_<?php echo $answer['ID']; ?>"
                                                   name="answers[<?php echo $question['ID']; ?>]"
                                                   value="<?php echo $answer['ID']; ?>"
                                                <?php echo (isset($savedAnswers[$question['ID']]) && $savedAnswers[$question['ID']] == $answer['ID']) ? 'checked' : ''; ?>
                                                   onchange="markQuestionAnswered(<?php echo $index; ?>)">
                                            <label for="answer_<?php echo $answer['ID']; ?>">
                                                <?php echo htmlspecialchars($answer['Text']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" name="submit_answers" class="btn submit-btn">Antworten absenden</button>
                </div>
            <?php endif; ?>
        </form>

        <!-- Formular-Informationen -->
        <div class="form-info">
            <h3>Hinweise zum Ausfüllen des Formulars</h3>
            <ul>
                <li>Beachten Sie, dass jede Frage genau eine Antwort erfordert.</li>
                <li>Sie haben <strong><?php echo gmdate("i:s", $timeLimit); ?> Minuten</strong> Zeit, um dieses Formular auszufüllen.</li>
                <li>Nach Ablauf der Zeit wird das Formular automatisch mit den bisher gegebenen Antworten abgesendet.</li>
                <li>Das Formular kann nach dem Absenden nicht mehr geändert werden.</li>
                <li>Bei Fragen wenden Sie sich bitte an die Wettkampfleitung.</li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<!-- CustomAlertBoxes -->
<div id="alert-container" style="display: none;">
    <?php
    // Alert für Zeitablauf
    echo CustomAlertBox::renderSimpleAlert(
        "timeoutAlert",
        "Zeit abgelaufen",
        "Die Zeit ist abgelaufen! Das Formular wird mit den bisher gegebenen Antworten abgesendet."
    );

    // Confirm für unvollständiges Formular
    $incompleteFormConfirm = new CustomAlertBox("incompleteFormConfirm");
    $incompleteFormConfirm->setTitle("Unvollständiges Formular");
    $incompleteFormConfirm->setMessage("Sie haben nicht alle Fragen beantwortet. Möchten Sie das Formular trotzdem absenden?");
    $incompleteFormConfirm->addButton("Ja", "", "btn", "button");
    $incompleteFormConfirm->addButton("Nein", "document.getElementById('incompleteFormConfirm').classList.remove('active');", "btn", "button");
    echo $incompleteFormConfirm->render();
    ?>
</div>

<!-- JavaScript-Konfiguration -->
<script>
    // Konfiguration für FormViewScript.js
    <?php if ($timerStarted && !$formSubmitted): ?>
    const formConfig = {
        formSubmitted: false,
        timeLimit: <?php echo $timeLimit; ?>,
        instanceToken: <?php echo json_encode_for_js($instanceToken); ?>,
        hasServerEndTime: <?php echo $hasServerEndTime ? 'true' : 'false'; ?>,
        serverEndTime: <?php echo $hasServerEndTime ? json_encode_for_js($serverEndTime) : 'null'; ?>,
        serverTime: <?php echo time(); ?>,
        questionCount: <?php echo count($questionsWithAnswers); ?>
    };
    <?php endif; ?>

    // Alert-Override-Funktionen
    function showCustomAlert(alertId, callback = null) {
        const alertElement = document.getElementById(alertId);
        if (!alertElement) {
            console.error(`AlertBox mit ID ${alertId} nicht gefunden!`);
            return;
        }

        document.getElementById('alert-container').style.display = 'block';
        alertElement.classList.add("active");

        if (callback) {
            const okButton = alertElement.querySelector("button");
            if (okButton) {
                const originalOnClick = okButton.onclick;
                okButton.onclick = function() {
                    if (originalOnClick) {
                        originalOnClick.call(this);
                    }
                    callback();
                    document.getElementById('alert-container').style.display = 'none';
                };
            }
        }
    }

    function submitIncompleteForm() {
        document.getElementById('incompleteFormConfirm').classList.remove('active');
        document.getElementById('alert-container').style.display = 'none';
        document.getElementById('question-form').submit();
    }

    // Fragen-Progress-Tracking
    function markQuestionAnswered(questionIndex) {
        const questionBox = document.getElementById(`question-box-${questionIndex}`);
        if (questionBox) {
            questionBox.classList.add('answered');
        }
        updateProgressStatus();
    }

    function updateProgressStatus() {
        const totalQuestions = <?php echo count($questionsWithAnswers); ?>;
        const answeredQuestions = document.querySelectorAll('.question-box.answered').length;

        const progressStatus = document.getElementById('progressStatus');
        const progressIndicator = document.getElementById('progressIndicator');

        if (progressStatus) {
            progressStatus.textContent = `${answeredQuestions} von ${totalQuestions} Fragen beantwortet`;
        }

        if (progressIndicator) {
            const percentage = totalQuestions > 0 ? (answeredQuestions / totalQuestions) * 100 : 0;
            progressIndicator.style.width = percentage + '%';
        }
    }

    // Initial Progress berechnen (für bereits gespeicherte Antworten)
    document.addEventListener('DOMContentLoaded', function() {
        updateProgressStatus();
    });
</script>

<!-- FormViewScript.js einbinden -->
<?php if ($timerStarted && !$formSubmitted): ?>
    <script src="../js/FormViewScript.js?v=<?php echo time(); ?>"></script>
<?php endif; ?>

<?php include '../php_assets/Footer.php'; ?>

</body>
</html>