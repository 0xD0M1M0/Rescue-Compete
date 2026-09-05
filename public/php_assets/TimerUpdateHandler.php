<?php
// TimerUpdateHandler.php - Verarbeitet AJAX-Anfragen für Timer-Updates und Formularabschlüsse

require_once '../db/DbConnection.php';
require_once '../model/TeamFormRelationModel.php';

// Kopfzeilen für JSON-Antwort und CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log-Funktion für Debugging
function logTimerAction($message, $data = null) {
    $logDir = '../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = "$logDir/timer_update.log";
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";

    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data);
    }

    $logMessage .= "\n";

    try {
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    } catch (Exception $e) {
        // Falls das Schreiben in die Log-Datei fehlschlägt, nicht den gesamten Handler abbrechen
    }
}

// Für OPTIONS-Anfragen bei CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Nur POST-Anfragen erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Nur POST-Anfragen erlaubt']);
    exit;
}

// Anforderliche Parameter prüfen
$token = isset($_POST['token']) ? trim($_POST['token']) : '';
$startTime = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
$forceReset = isset($_POST['force_reset']) && $_POST['force_reset'] === '1';
$statusCheck = isset($_POST['status_check']) && $_POST['status_check'] === '1';
$autoSubmit = isset($_POST['auto_submit']) && $_POST['auto_submit'] === '1';
$clientTimezone = isset($_POST['client_timezone']) ? trim($_POST['client_timezone']) : 'UTC';

// Log der Anfrage
logTimerAction("Neue Anfrage erhalten", [
    'token' => $token,
    'startTime' => $startTime,
    'forceReset' => $forceReset,
    'statusCheck' => $statusCheck,
    'autoSubmit' => $autoSubmit,
    'clientTimezone' => $clientTimezone
]);

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token ist erforderlich']);
    exit;
}

try {
    // Datenbankverbindung herstellen
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new Exception('Datenbankverbindung nicht verfügbar');
    }

    // TeamForm-Modell instanzieren
    $teamFormModel = new TeamForm\TeamFormRelationModel($conn);

    // TeamForm anhand des Tokens abrufen
    $teamForm = $teamFormModel->getTeamFormByToken($token);
    if (!$teamForm) {
        throw new Exception('Formular mit dem angegebenen Token nicht gefunden');
    }

    // Formular-ID und Team-ID aus dem Token abrufen
    $formId = $teamForm['form_ID'];
    $teamId = $teamForm['team_ID'];
    $timeLimit = isset($teamForm['time_limit']) ? intval($teamForm['time_limit']) : 180; // Standard: 3 Minuten

    // Status-Check - Gibt zurück, ob das Formular abgeschlossen wurde oder abgelaufen ist
    if ($statusCheck) {
        $completed = $teamForm['completed'] == 1;
        $startTime = $teamForm['start_time'];
        $now = new DateTime();

        if ($completed) {
            echo json_encode([
                'success' => true,
                'status' => 'completed',
                'message' => 'Formular bereits abgeschlossen',
                'formId' => $formId,
                'teamId' => $teamId
            ]);
            exit;
        }

        if ($startTime) {
            $startTimeObj = new DateTime($startTime);
            $endTimeObj = clone $startTimeObj;
            $endTimeObj->add(new DateInterval('PT' . $timeLimit . 'S'));

            if ($now > $endTimeObj) {
                echo json_encode([
                    'success' => true,
                    'status' => 'expired',
                    'message' => 'Formular abgelaufen',
                    'formId' => $formId,
                    'teamId' => $teamId,
                    'startTime' => $startTime,
                    'endTime' => $endTimeObj->format('Y-m-d H:i:s'),
                    'remainingSeconds' => 0
                ]);
            } else {
                $remainingSeconds = $endTimeObj->getTimestamp() - $now->getTimestamp();
                echo json_encode([
                    'success' => true,
                    'status' => 'running',
                    'message' => 'Formular aktiv',
                    'formId' => $formId,
                    'teamId' => $teamId,
                    'startTime' => $startTime,
                    'endTime' => $endTimeObj->format('Y-m-d H:i:s'),
                    'remainingSeconds' => $remainingSeconds
                ]);
            }
            exit;
        }

        echo json_encode([
            'success' => true,
            'status' => 'not_started',
            'message' => 'Formular noch nicht gestartet',
            'formId' => $formId,
            'teamId' => $teamId
        ]);
        exit;
    }

    // Automatische Formularabsendung, wenn der Timer abgelaufen ist
    if ($autoSubmit) {
        // Überprüfen, ob das Formular bereits abgeschlossen ist
        if ($teamForm['completed'] == 1) {
            echo json_encode([
                'success' => true,
                'message' => 'Formular wurde bereits abgeschlossen',
                'formId' => $formId,
                'teamId' => $teamId,
                'status' => 'completed'
            ]);
            exit;
        }

        // Überprüfen, ob die Zeit wirklich abgelaufen ist
        $startTime = $teamForm['start_time'];
        if ($startTime) {
            $startTimeObj = new DateTime($startTime);
            $endTimeObj = clone $startTimeObj;
            $endTimeObj->add(new DateInterval('PT' . $timeLimit . 'S'));
            $now = new DateTime();

            if ($now > $endTimeObj) {
                // Zeit ist abgelaufen, Formular als abgeschlossen markieren
                $completed = $teamFormModel->updateFormCompletion($teamId, $formId, true, 0, $now->format('Y-m-d H:i:s'));

                if ($completed) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Formular automatisch als abgeschlossen markiert (Zeit abgelaufen)',
                        'formId' => $formId,
                        'teamId' => $teamId,
                        'status' => 'expired',
                        'redirect' => 'FormView.php?token=' . $token . '&expired=1'
                    ]);

                    logTimerAction("Formular automatisch abgeschlossen (Zeit abgelaufen)", [
                        'token' => $token,
                        'formId' => $formId,
                        'teamId' => $teamId
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Fehler beim automatischen Abschließen des Formulars',
                        'formId' => $formId,
                        'teamId' => $teamId
                    ]);
                }
                exit;
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Die Zeit ist noch nicht abgelaufen',
                    'formId' => $formId,
                    'teamId' => $teamId,
                    'remainingSeconds' => $endTimeObj->getTimestamp() - $now->getTimestamp()
                ]);
                exit;
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Timer wurde noch nicht gestartet',
                'formId' => $formId,
                'teamId' => $teamId
            ]);
            exit;
        }
    }

    // Startzeit-Update-Anfrage verarbeiten
    if (!empty($startTime) || $forceReset) {
        // Wenn erzwungenes Reset oder keine Startzeit gesetzt ist, setze die aktuelle Zeit
        $newStartTime = $forceReset || empty($teamForm['start_time']) ?
            date('Y-m-d H:i:s') : $teamForm['start_time'];

        if (!empty($startTime) && !$forceReset) {
            // Wenn eine spezifische Startzeit angegeben wurde und kein Reset erzwungen wird
            try {
                $startTimeObj = new DateTime($startTime);
                $newStartTime = $startTimeObj->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Bei ungültigem Datumsformat die aktuelle Zeit verwenden
                $newStartTime = date('Y-m-d H:i:s');
                logTimerAction("Ungültiges Datumsformat: " . $startTime . ", verwende aktuelle Zeit");
            }
        }

        // Startzeit in der Datenbank aktualisieren
        $stmt = $conn->prepare(
            "UPDATE TeamForm SET start_time = :startTime 
             WHERE team_ID = :teamId AND form_ID = :formId"
        );
        $stmt->bindParam(':startTime', $newStartTime);
        $stmt->bindParam(':teamId', $teamId, PDO::PARAM_INT);
        $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);
        $success = $stmt->execute();

        if ($success) {
            // Berechne die Endzeit und verbleibende Zeit
            $startTimeObj = new DateTime($newStartTime);
            $endTimeObj = clone $startTimeObj;
            $endTimeObj->add(new DateInterval('PT' . $timeLimit . 'S'));
            $now = new DateTime();
            $remainingSeconds = max(0, $endTimeObj->getTimestamp() - $now->getTimestamp());

            echo json_encode([
                'success' => true,
                'message' => 'Timer-Startzeit erfolgreich aktualisiert',
                'formId' => $formId,
                'teamId' => $teamId,
                'startTime' => $newStartTime,
                'endTime' => $endTimeObj->format('Y-m-d H:i:s'),
                'remainingSeconds' => $remainingSeconds,
                'status' => $remainingSeconds > 0 ? 'running' : 'expired'
            ]);

            logTimerAction("Timer aktualisiert", [
                'token' => $token,
                'formId' => $formId,
                'teamId' => $teamId,
                'startTime' => $newStartTime,
                'endTime' => $endTimeObj->format('Y-m-d H:i:s'),
                'remainingSeconds' => $remainingSeconds
            ]);
        } else {
            throw new Exception('Fehler beim Aktualisieren der Timer-Startzeit');
        }
    } else {
        // Wenn keine Startzeit angegeben wurde, gib die aktuelle Timer-Informationen zurück
        $startTime = $teamForm['start_time'];

        if ($startTime) {
            $startTimeObj = new DateTime($startTime);
            $endTimeObj = clone $startTimeObj;
            $endTimeObj->add(new DateInterval('PT' . $timeLimit . 'S'));
            $now = new DateTime();
            $remainingSeconds = max(0, $endTimeObj->getTimestamp() - $now->getTimestamp());

            echo json_encode([
                'success' => true,
                'message' => 'Timer-Informationen abgerufen',
                'formId' => $formId,
                'teamId' => $teamId,
                'startTime' => $startTime,
                'endTime' => $endTimeObj->format('Y-m-d H:i:s'),
                'remainingSeconds' => $remainingSeconds,
                'status' => $remainingSeconds > 0 ? 'running' : 'expired'
            ]);

            logTimerAction("Timer-Informationen abgerufen", [
                'token' => $token,
                'formId' => $formId,
                'teamId' => $teamId,
                'startTime' => $startTime,
                'endTime' => $endTimeObj->format('Y-m-d H:i:s'),
                'remainingSeconds' => $remainingSeconds
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Kein Timer gestartet',
                'formId' => $formId,
                'teamId' => $teamId,
                'status' => 'not_started'
            ]);
        }
    }
} catch (Exception $e) {
    logTimerAction("Fehler: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ein Fehler ist aufgetreten.']);
}