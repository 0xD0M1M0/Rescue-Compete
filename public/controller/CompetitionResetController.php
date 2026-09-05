<?php
namespace Competition;

require_once '../model/CompetitionResetModel.php';

use Competition\CompetitionResetModel;

/**
 * Controller zum Zurücksetzen des Wettkampfs durch Löschen verschiedener Datensätze
 * und ihrer Abhängigkeiten/Verbindungen in der Datenbank.
 */
class CompetitionResetController
{
    private CompetitionResetModel $model;
    private string $message = "";
    private string $messageType = "info";

    /**
     * Konstruktor: Initialisiert den Controller mit einem Model
     *
     * @param \PDO $db Die Datenbankverbindung
     */
    public function __construct(\PDO $db)
    {
        $this->model = new CompetitionResetModel($db);
    }

    /**
     * Verarbeitet Anfragen zum Zurücksetzen des Wettkampfs
     *
     * @return void
     */
    public function handleRequest(): void
    {
        // Nur POST-Requests verarbeiten
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
        enforce_post_same_origin();
        csrf_require();

        // Prüfen, ob eine Bestätigung vorliegt
        if (!isset($_POST['confirm']) || $_POST['confirm'] !== "1") {
            $this->setMessage("Bitte bestätigen Sie die Löschung durch Aktivieren der Checkbox.", "error");
            return;
        }

        // Verschiedene Reset-Aktionen verarbeiten
        try {
            if (isset($_POST['reset_staffeln'])) {
                $this->resetStaffeln();
            } elseif (isset($_POST['reset_stationen'])) {
                $this->resetStationen();
            } elseif (isset($_POST['reset_protokolle'])) {
                $this->resetProtokolle();
            } elseif (isset($_POST['reset_mannschaften'])) {
                $this->resetMannschaften();
            } elseif (isset($_POST['reset_formulare'])) {
                $this->resetFormulare();
            } elseif (isset($_POST['reset_wertungen'])) {
                $this->resetWertungen();
            } elseif (isset($_POST['reset_users'])) {
                $this->resetUsers();
            } elseif (isset($_POST['reset_all'])) {
                $this->resetAll();
            }
        } catch (\Exception $e) {
            $this->setMessage("Unerwarteter Fehler: " . $e->getMessage(), "error");
        }
    }

    /**
     * Setzt alle Staffeln und deren Verbindungen zurück
     */
    private function resetStaffeln(): void
    {
        $result = $this->model->resetStaffeln();
        if ($result['success']) {
            $this->setMessage("Alle Staffeln und ihre Verbindungen wurden erfolgreich gelöscht.", "success");
        } else {
            $this->setMessage("Fehler beim Löschen der Staffeln: " . $result['error'], "error");
        }
    }

    /**
     * Setzt alle Stationen und deren Verbindungen zurück
     */
    private function resetStationen(): void
    {
        $result = $this->model->resetStationen();
        if ($result['success']) {
            $this->setMessage("Alle Stationen und ihre Verbindungen wurden erfolgreich gelöscht.", "success");
        } else {
            $this->setMessage("Fehler beim Löschen der Stationen: " . $result['error'], "error");
        }
    }

    /**
     * Setzt alle Protokolle und deren Verbindungen zurück
     */
    private function resetProtokolle(): void
    {
        $result = $this->model->resetProtokolle();
        if ($result['success']) {
            $this->setMessage("Alle Protokolle und ihre Verbindungen wurden erfolgreich gelöscht.", "success");
        } else {
            $this->setMessage("Fehler beim Löschen der Protokolle: " . $result['error'], "error");
        }
    }

    /**
     * Setzt alle Mannschaften und deren Verbindungen zurück
     */
    private function resetMannschaften(): void
    {
        $result = $this->model->resetMannschaften();
        if ($result['success']) {
            $this->setMessage("Alle Mannschaften und ihre Verbindungen wurden erfolgreich gelöscht.", "success");
        } else {
            $this->setMessage("Fehler beim Löschen der Mannschaften: " . $result['error'], "error");
        }
    }

    /**
     * Setzt alle Formulare und deren Verbindungen zurück
     */
    private function resetFormulare(): void
    {
        $result = $this->model->resetFormulare();
        if ($result['success']) {
            $this->setMessage("Alle Formulare und ihre Verbindungen wurden erfolgreich gelöscht. Die Fragenpools bleiben erhalten.", "success");
        } else {
            $this->setMessage("Fehler beim Löschen der Formulare: " . $result['error'], "error");
        }
    }

    /**
     * Setzt alle Wertungen und deren Verbindungen zurück
     */
    private function resetWertungen(): void
    {
        $result = $this->model->resetWertungen();
        if ($result['success']) {
            $this->setMessage("Alle Wertungsklassen und ihre Verbindungen wurden erfolgreich gelöscht.", "success");
        } else {
            $this->setMessage("Fehler beim Löschen der Wertungsklassen: " . $result['error'], "error");
        }
    }

    /**
     * Setzt alle Benutzer zurück, außer dem aktuell angemeldeten
     */
    private function resetUsers(): void
    {
        $currentUserId = $_SESSION['id'] ?? null;

        if (!$currentUserId) {
            $this->setMessage("Konnte den aktuellen Benutzer nicht identifizieren. Es wurden keine Benutzer gelöscht.", "error");
            return;
        }

        $result = $this->model->resetUsers($currentUserId);
        if ($result['success']) {
            $deletedCount = $result['deletedCount'] ?? 0;
            $this->setMessage("{$deletedCount} Benutzer wurden erfolgreich gelöscht. Der aktuelle Benutzer wurde beibehalten.", "success");
        } else {
            $this->setMessage("Fehler beim Löschen der Benutzer: " . $result['error'], "error");
        }
    }

    /**
     * Setzt alle Wettkampfdaten zurück
     */
    private function resetAll(): void
    {
        $currentUserId = $_SESSION['id'] ?? null;

        if (!$currentUserId) {
            $this->setMessage("Konnte den aktuellen Benutzer nicht identifizieren. Reset abgebrochen.", "error");
            return;
        }

        $result = $this->model->resetAll($currentUserId);
        if ($result['success']) {
            $this->setMessage("Alle Wettkampfdaten wurden erfolgreich zurückgesetzt.", "success");
        } else {
            $this->setMessage("Fehler beim Zurücksetzen des Wettkampfs: " . $result['error'], "error");
        }
    }

    /**
     * Setzt eine Nachricht mit Typ
     *
     * @param string $message Die Nachricht
     * @param string $type Der Typ (success, error, info)
     */
    private function setMessage(string $message, string $type): void
    {
        $this->message = $message;
        $this->messageType = $type;
    }

    /**
     * Gibt die aktuelle Nachricht zurück
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Gibt den aktuellen Nachrichtentyp zurück
     *
     * @return string
     */
    public function getMessageType(): string
    {
        return $this->messageType;
    }
}