<?php
namespace Station;

class StationInputController {
    private StationModel $model;
    public string $message = "";
    public array $modalData = [];
    public string $redirectUrl = "StationInputView.php";

    public function __construct(StationModel $model) {
        $this->model = $model;
    }

    public function handleRequest() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
            enforce_post_same_origin();
        }

        // AJAX-Request: zugeordnete + alle Stationen einer Wertung (für Checkbox-Vorbelegung)
        if (isset($_GET['action']) && $_GET['action'] === 'getStationsForWertung') {
            $this->handleAjaxGetStationsForWertung();
            return;
        }

        // Stationen einer Wertung zuordnen (Checkbox-Sync: ersetzt die bisherige Auswahl)
        if (isset($_POST['assign_stationen'])) {
            $wertungId = intval($_POST['wertung'] ?? 0);
            $stationIds = [];
            if (isset($_POST['stationen']) && is_array($_POST['stationen'])) {
                $stationIds = array_map('intval', $_POST['stationen']);
            }

            if ($wertungId <= 0) {
                $this->message = "Bitte wählen Sie eine Wertung aus.";
            } elseif ($this->model->setStationsForWertung($wertungId, $stationIds)) {
                if (session_status() == PHP_SESSION_NONE) {
                    require_once __DIR__ . '/../CookieMonster.php';
                    startSecureSession();
                }
                $count = count($stationIds);
                $_SESSION['success_message'] = $count === 1
                    ? "1 Station der Wertung zugeordnet."
                    : "$count Stationen der Wertung zugeordnet.";
                header("Location: " . $this->redirectUrl . "?view=assign");
                exit;
            } else {
                $this->message = "Fehler beim Speichern der Zuordnung.";
            }
        }

        // Löschen einer Station
        if (isset($_POST['delete_station'])) {
            $id = intval($_POST['delete_ID']);
            if ($this->model->delete($id)) {
                header("Location: " . $this->redirectUrl);
                exit;
            } else {
                $this->message = "Fehler beim Löschen der Station.";
            }
        }

        // Hinzufügen oder Aktualisieren einer Station
        if (isset($_POST['add_station'])) {
            $name = trim($_POST['name']);
            $nr = intval(trim($_POST['Nr']));

            // Prüfe auf doppelten Namen
            $existingNameId = $this->model->existsByName($name);
            if ($existingNameId) {
                $this->modalData = ['duplicateName' => true];
                return;
            }

            // Prüfe auf doppelte Stationsnummer
            $existingNrId = $this->model->existsByNr($nr);
            if ($existingNrId) {
                $this->modalData = ['duplicateNumber' => true, 'number' => $nr];
                return;
            }

            // Wenn keine Duplikate gefunden, erstelle neue Station
            $result = $this->model->create(['name' => $name, 'Nr' => $nr]);
            if ($result) {
                header("Location: " . $this->redirectUrl);
                exit;
            } else {
                $this->message = 'Fehler beim Erstellen der Station.';
            }
        }
    }

    /**
     * AJAX: Liefert alle Stationen plus die einer Wertung bereits zugeordneten IDs
     * als JSON. Wird über StationInputView.php?action=... aufgerufen, sodass das
     * Rechte-Gate der View vorher greift.
     */
    private function handleAjaxGetStationsForWertung(): void
    {
        header('Content-Type: application/json');

        $wertungId = intval($_GET['wertung'] ?? 0);
        if ($wertungId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Keine Wertung angegeben']);
            exit;
        }

        $alleStationen = $this->model->read();
        $zugeordneteIds = $this->model->getStationsByWertung($wertungId);

        echo json_encode([
            'success'        => true,
            'alleStationen'  => $alleStationen,
            'zugeordneteIds' => $zugeordneteIds,
        ]);
        exit;
    }
}