<?php
namespace TeamForm;

use PDO;
use PDOException;

class TeamFormRelationModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Fügt eine start_time Spalte zur TeamForm-Tabelle hinzu, falls noch nicht vorhanden
     * Diese Methode sollte während der Installation/Update der Anwendung ausgeführt werden
     */
    public function addStartTimeColumn(): bool
    {
        try {
            // Prüfen, ob die Spalte bereits existiert
            $stmt = $this->db->prepare("SHOW COLUMNS FROM TeamForm LIKE 'start_time'");
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                // Spalte existiert nicht, daher hinzufügen
                $this->db->exec("ALTER TABLE TeamForm ADD COLUMN start_time DATETIME NULL");
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error in TeamFormRelationModel::addStartTimeColumn: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Setzt den Timer-Start für ein Formular
     *
     * @param int $teamId ID der Mannschaft
     * @param int $formId ID des Formulars
     * @param string|null $startTime Startzeit als SQL DATETIME-String oder null für aktuelle Zeit
     * @return bool True bei Erfolg, false bei Fehler
     */
    public function setFormStartTime(int $teamId, int $formId, ?string $startTime = null): bool
    {
        try {
            // Wenn keine Startzeit angegeben wurde, aktuelle Zeit verwenden
            if ($startTime === null) {
                $startTime = date('Y-m-d H:i:s');
            }

            $stmt = $this->db->prepare(
                "UPDATE TeamForm 
                 SET start_time = :startTime 
                 WHERE team_ID = :teamId AND form_ID = :formId"
            );

            $stmt->bindParam(':startTime', $startTime);
            $stmt->bindParam(':teamId', $teamId, PDO::PARAM_INT);
            $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in TeamFormRelationModel::setFormStartTime: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Holt die Startzeit eines Formulars
     *
     * @param int $teamId ID der Mannschaft
     * @param int $formId ID des Formulars
     * @return string|null Startzeit als SQL DATETIME-String oder null wenn nicht gefunden
     */
    public function getFormStartTime(int $teamId, int $formId): ?string
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT start_time FROM TeamForm 
                 WHERE team_ID = :teamId AND form_ID = :formId"
            );

            $stmt->bindParam(':teamId', $teamId, PDO::PARAM_INT);
            $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? $result['start_time'] : null;
        } catch (PDOException $e) {
            error_log("Error in TeamFormRelationModel::getFormStartTime: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verbindet ein Formular mit einer Mannschaft
     *
     * @param int $teamId ID der Mannschaft
     * @param int $formId ID des Formulars
     * @param int $sequence Optionale Reihenfolge (falls Formulare in einer bestimmten Reihenfolge abgearbeitet werden sollen)
     * @return bool True bei Erfolg, false bei Fehler
     */
    public function assignFormToTeam(int $teamId, int $formId, int $sequence = 0): bool
    {
        try {
            // Prüfen, ob die Zuweisung bereits existiert
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM TeamForm 
                WHERE team_ID = :teamId AND form_ID = :formId"
            );
            $stmt->bindParam(':teamId', $teamId, PDO::PARAM_INT);
            $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->fetchColumn() > 0) {
                // Aktualisieren der Sequenz, falls die Verbindung bereits besteht
                $stmt = $this->db->prepare(
                    "UPDATE TeamForm 
                    SET sequence = :sequence 
                    WHERE team_ID = :teamId AND form_ID = :formId"
                );
                $stmt->bindParam(':teamId', $teamId, PDO::PARAM_INT);
                $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);
                $stmt->bindParam(':sequence', $sequence, PDO::PARAM_INT);
                return $stmt->execute();
            }

            // Neue Zuweisung erstellen
            $stmt = $this->db->prepare(
                "INSERT INTO TeamForm (team_ID, form_ID, sequence, completed, points) 
                VALUES (:teamId, :formId, :sequence, 0, 0)"
            );
            $stmt->bindParam(':teamId', $teamId, PDO::PARAM_INT);
            $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);
            $stmt->bindParam(':sequence', $sequence, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in TeamFormRelationModel::assignFormToTeam: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Aktualisiert den Status eines Formulars für eine Mannschaft
     *
     * @param int $teamId ID der Mannschaft
     * @param int $formId ID des Formulars
     * @param bool $completed Status (abgeschlossen oder nicht)
     * @param int $points Erreichte Punkte
     * @param string|null $completionDate Datum und Uhrzeit des Abschlusses
     * @return bool True bei Erfolg, false bei Fehler
     */
    public function updateFormCompletion(int $teamId, int $formId, bool $completed, int $points = 0, string $completionDate = null): bool
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE TeamForm 
                SET completed = :completed, 
                    points = :points, 
                    completion_date = :completionDate
                WHERE team_ID = :teamId AND form_ID = :formId"
            );

            $stmt->bindParam(':teamId', $teamId, PDO::PARAM_INT);
            $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);
            $stmt->bindParam(':completed', $completed, PDO::PARAM_BOOL);
            $stmt->bindParam(':points', $points, PDO::PARAM_INT);
            $stmt->bindParam(':completionDate', $completionDate);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in TeamFormRelationModel::updateFormCompletion: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generiert ein eindeutiges Token für eine Team-Formular-Beziehung
     *
     * @return string Eindeutiges Token
     */
    public function generateUniqueToken($length = 16): string
    {
        // $length kept for BC; tokens are always 32 hex chars (16 bytes)
        do {
            $token = bin2hex(random_bytes(16));
            $exists = $this->tokenExists($token);
        } while ($exists);

        return $token;
    }

    /**
     * Prüft, ob ein Token bereits in der Datenbank existiert
     *
     * @param string $token Das zu prüfende Token
     * @return bool True wenn das Token existiert, sonst false
     */
    public function tokenExists(string $token): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM TeamForm WHERE token = :token"
            );
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $stmt->execute();
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error in TeamFormRelationModel::tokenExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Holt ein TeamForm-Objekt anhand eines Tokens
     *
     * @param string $token Das Token des Formulars
     * @return array|null Die vollständigen Daten des Formulars oder null bei Fehler
     */
    public function getTeamFormByToken(string $token): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT tf.*, qf.Titel, qf.time_limit, s.name AS station_name
             FROM TeamForm tf
             JOIN QuestionForm qf ON tf.form_ID = qf.ID
             JOIN Station s ON qf.Station_ID = s.ID
             WHERE tf.token = :token"
            );
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error in TeamFormRelationModel::getTeamFormByToken: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verbindet ein Formular mit einer Mannschaft und erstellt ein Token
     *
     * @param int $teamId ID der Mannschaft
     * @param int $formId ID des Formulars
     * @param int $sequence Optionale Reihenfolge
     * @return bool True bei Erfolg, false bei Fehler
     */
    public function assignFormToTeamWithToken(int $teamId, int $formId, int $sequence = 0): bool
    {
        try {
            // Generiere ein eindeutiges Token
            $token = $this->generateUniqueToken();

            // Prüfe, ob die Zuweisung bereits existiert
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM TeamForm 
             WHERE team_ID = :teamId AND form_ID = :formId"
            );
            $stmt->bindParam(':teamId', $teamId, PDO::PARAM_INT);
            $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->fetchColumn() > 0) {
                // Aktualisiere bestehende Zuweisung
                $stmt = $this->db->prepare(
                    "UPDATE TeamForm 
                 SET sequence = :sequence, token = :token
                 WHERE team_ID = :teamId AND form_ID = :formId"
                );
                $stmt->bindParam(':teamId', $teamId, PDO::PARAM_INT);
                $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);
                $stmt->bindParam(':sequence', $sequence, PDO::PARAM_INT);
                $stmt->bindParam(':token', $token, PDO::PARAM_STR);
                return $stmt->execute();
            } else {
                // Erstelle neue Zuweisung
                $stmt = $this->db->prepare(
                    "INSERT INTO TeamForm (team_ID, form_ID, sequence, completed, points, token) 
                 VALUES (:teamId, :formId, :sequence, 0, 0, :token)"
                );
                $stmt->bindParam(':teamId', $teamId, PDO::PARAM_INT);
                $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);
                $stmt->bindParam(':sequence', $sequence, PDO::PARAM_INT);
                $stmt->bindParam(':token', $token, PDO::PARAM_STR);
                return $stmt->execute();
            }
        } catch (PDOException $e) {
            error_log("Error in TeamFormRelationModel::assignFormToTeamWithToken: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Weist ein Formular an alle Teams zu
     *
     * @param int $formId ID des Formulars
     * @return int Anzahl der Teams, denen das Formular zugewiesen wurde
     */
    public function assignFormToAllTeams(int $formId): int
    {
        try {
            // Hole alle Teams
            $stmtTeams = $this->db->query("SELECT ID FROM Mannschaft");
            $teams = $stmtTeams->fetchAll(PDO::FETCH_COLUMN);

            $count = 0;
            foreach ($teams as $teamId) {
                // Für jedes Team Token erzeugen und Formular zuweisen
                if ($this->assignFormToTeamWithToken($teamId, $formId)) {
                    $count++;
                }
            }

            return $count;
        } catch (PDOException $e) {
            error_log("Error in TeamFormRelationModel::assignFormToAllTeams: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Entfernt ein Formular von einem Team
     *
     * @param int $teamId ID des Teams
     * @param int $formId ID des Formulars
     * @return bool True bei Erfolg, sonst false
     */
    public function removeFormFromTeam(int $teamId, int $formId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM TeamForm 
             WHERE team_ID = :teamId AND form_ID = :formId"
            );
            $stmt->bindParam(':teamId', $teamId, PDO::PARAM_INT);
            $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in TeamFormRelationModel::removeFormFromTeam: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Weist alle Formulare einer Station einem Team zu
     *
     * @param int $teamId ID des Teams
     * @param int $stationId ID der Station
     * @return int Anzahl der zugewiesenen Formulare
     */
    public function assignAllFormsByStationToTeam(int $teamId, int $stationId): int
    {
        try {
            // Hole alle Formulare für die gegebene Station
            $stmtForms = $this->db->prepare(
                "SELECT ID FROM QuestionForm 
             WHERE Station_ID = :stationId"
            );
            $stmtForms->bindParam(':stationId', $stationId, PDO::PARAM_INT);
            $stmtForms->execute();
            $forms = $stmtForms->fetchAll(PDO::FETCH_COLUMN);

            $count = 0;
            foreach ($forms as $formId) {
                // Jedem Formular ein Token zuweisen und dem Team zuordnen
                if ($this->assignFormToTeamWithToken($teamId, $formId)) {
                    $count++;
                }
            }

            return $count;
        } catch (PDOException $e) {
            error_log("Error in TeamFormRelationModel::assignAllFormsByStationToTeam: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Holt alle Formulare für eine bestimmte Mannschaft mit Status
     *
     * @param int $teamId ID der Mannschaft
     * @return array Liste der Formulare mit Status
     */
    public function getFormsByTeam(int $teamId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT tf.*, qf.Titel, s.name AS station_name 
             FROM TeamForm tf
             JOIN QuestionForm qf ON tf.form_ID = qf.ID
             JOIN Station s ON qf.Station_ID = s.ID
             WHERE tf.team_ID = :teamId
             ORDER BY tf.sequence, qf.Titel"
            );
            $stmt->bindParam(':teamId', $teamId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in TeamFormRelationModel::getFormsByTeam: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Holt Statistiken für ein bestimmtes Formular über alle Teams
     *
     * @param int $formId ID des Formulars
     * @return array Statistikdaten zum Formular
     */
    public function getTeamStatsByForm(int $formId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 
                    COUNT(tf.team_ID) AS total_count,
                    SUM(CASE WHEN tf.completed = 1 THEN 1 ELSE 0 END) AS completed_count,
                    SUM(tf.points) AS total_points
                FROM TeamForm tf
                WHERE tf.form_ID = :formId"
            );
            $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Durchschnittliche Punktzahl berechnen
            $completedCount = $stats['completed_count'] ?? 0;
            $totalPoints = $stats['total_points'] ?? 0;
            $averagePoints = $completedCount > 0 ? $totalPoints / $completedCount : 0;

            return [
                'total_count' => (int)($stats['total_count'] ?? 0),
                'completed_count' => (int)$completedCount,
                'total_points' => (int)$totalPoints,
                'average_points' => round($averagePoints, 1)
            ];
        } catch (PDOException $e) {
            error_log("Error in TeamFormRelationModel::getTeamStatsByForm: " . $e->getMessage());
            return [
                'total_count' => 0,
                'completed_count' => 0,
                'total_points' => 0,
                'average_points' => 0.0
            ];
        }
    }

    /**
     * Holt alle Mannschaften mit Formularzuweisungsstatistik
     *
     * @return array Liste aller Mannschaften mit Anzahl zugewiesener Formulare
     */
    public function getAllTeamsWithFormsCount(): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT m.ID, m.Teamname, m.Kreisverband, m.Landesverband,
                COUNT(tf.form_ID) AS total_forms,
                SUM(CASE WHEN tf.completed = 1 THEN 1 ELSE 0 END) AS completed_forms,
                SUM(tf.points) AS total_points
                FROM Mannschaft m
                LEFT JOIN TeamForm tf ON m.ID = tf.team_ID
                GROUP BY m.ID
                ORDER BY m.Teamname"
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in TeamFormRelationModel::getAllTeamsWithFormsCount: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Prüft, ob ein Formular mit einem laufenden Timer existiert und sendet es ab, falls die Zeit abgelaufen ist
     * Kann als Cron-Job regelmäßig aufgerufen werden
     * Debug-Version mit ausführlicher Protokollierung
     *
     * @return array Statistik über bearbeitete Formulare
     */
    public function checkAndSubmitExpiredForms(): array
    {
        // Debug-Log-Datei
        $logFile = '../logs/timer_relation_debug.log';
        $debugLog = fopen($logFile, 'a');

        function debugTimerLog($message) {
            global $debugLog;
            fwrite($debugLog, "[" . date('Y-m-d H:i:s') . "] " . $message . "\n");
        }

        debugTimerLog("=== checkAndSubmitExpiredForms gestartet ===");

        try {
            $now = date('Y-m-d H:i:s');
            debugTimerLog("Aktuelle Serverzeit: $now");

            $stats = [
                'processed' => 0,
                'expired' => 0,
                'submitted' => 0,
                'errors' => 0,
                'details' => []
            ];

            // Hole alle Formulare mit Startzeit und die noch nicht abgeschlossen sind
            debugTimerLog("Suche Formulare mit Startzeit, die noch nicht abgeschlossen sind");
            $stmt = $this->db->prepare(
                "SELECT tf.team_ID, tf.form_ID, tf.start_time, tf.token, qf.time_limit,
            m.Teamname, qf.Titel as form_titel
             FROM TeamForm tf
             JOIN QuestionForm qf ON tf.form_ID = qf.ID
             JOIN Mannschaft m ON tf.team_ID = m.ID
             WHERE tf.completed = 0 
             AND tf.start_time IS NOT NULL"
            );
            $stmt->execute();
            $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stats['processed'] = count($forms);
            debugTimerLog("Gefunden: {$stats['processed']} Formulare mit laufendem Timer");

            foreach ($forms as $form) {
                $teamId = $form['team_ID'];
                $formId = $form['form_ID'];
                $token = $form['token'];
                $startTime = $form['start_time'];
                $timeLimit = intval($form['time_limit']);
                $teamName = $form['Teamname'];
                $formTitle = $form['form_titel'];

                debugTimerLog("Prüfe Formular: '$formTitle' für Team '$teamName' [ID: $formId, Team: $teamId, Token: $token]");
                debugTimerLog("  Startzeit: $startTime, Zeitlimit: $timeLimit Sekunden");

                // Berechne die Endzeit des Formulars
                $startTimeObj = new \DateTime($startTime);
                $endTimeObj = clone $startTimeObj;
                $endTimeObj->add(new \DateInterval('PT' . $timeLimit . 'S'));
                $endTime = $endTimeObj->format('Y-m-d H:i:s');

                debugTimerLog("  Berechnete Endzeit: $endTime");

                // Debug: Berechne verbleibende Zeit
                $nowObj = new \DateTime($now);
                $timeLeftSeconds = $nowObj->getTimestamp() - $endTimeObj->getTimestamp();
                debugTimerLog("  Verbleibende Zeit: " . abs($timeLeftSeconds) . " Sekunden " .
                    ($timeLeftSeconds >= 0 ? "abgelaufen" : "verbleibend"));

                // Wenn die Endzeit erreicht ist, das Formular als abgeschlossen markieren
                if ($endTimeObj <= $nowObj) {
                    $stats['expired']++;
                    debugTimerLog("  Timer abgelaufen! Markiere als abgeschlossen.");

                    // Formular als abgeschlossen markieren mit 0 Punkten
                    if ($this->updateFormCompletion(
                        $teamId,
                        $formId,
                        true,
                        0,
                        $now
                    )) {
                        $stats['submitted']++;
                        debugTimerLog("  Formular erfolgreich als abgeschlossen markiert.");
                        $stats['details'][] = "Formular '$formTitle' für Team '$teamName' wurde als abgeschlossen markiert.";
                    } else {
                        $stats['errors']++;
                        debugTimerLog("  FEHLER: Formular konnte nicht als abgeschlossen markiert werden!");
                        $stats['details'][] = "Fehler beim Markieren von Formular '$formTitle' für Team '$teamName'.";
                    }
                } else {
                    debugTimerLog("  Timer läuft noch, keine Aktion erforderlich.");
                }
            }

            fclose($debugLog);
            return $stats;
        } catch (PDOException $e) {
            // Bei Fehler Transaktion zurückrollen
            debugTimerLog("FEHLER in checkAndSubmitExpiredForms: " . $e->getMessage());

            fclose($debugLog);
            return [
                'processed' => 0,
                'expired' => 0,
                'submitted' => 0,
                'errors' => 1,
                'error_message' => $e->getMessage()
            ];
        }
    }
}