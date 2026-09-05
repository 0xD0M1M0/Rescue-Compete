<?php
namespace Staffel;

class StaffelInputController {
    private StaffelModel $model;
    public string $message = "";
    public array $modalData = [];
    public string $redirectUrl = "StaffelInputView.php";

    public function __construct(StaffelModel $model) {
        $this->model = $model;
    }

    public function handleRequest() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
            enforce_post_same_origin();
        }

        // AJAX-Request: zugeordnete + alle Staffeln einer Wertung (für Checkbox-Vorbelegung)
        if (isset($_GET['action']) && $_GET['action'] === 'getStaffelnForWertung') {
            $this->handleAjaxGetStaffelnForWertung();
            return;
        }

        // Staffeln einer Wertung zuordnen (Checkbox-Sync: ersetzt die bisherige Auswahl)
        if (isset($_POST['assign_staffeln'])) {
            $wertungId = intval($_POST['wertung'] ?? 0);
            $staffelIds = [];
            if (isset($_POST['staffeln']) && is_array($_POST['staffeln'])) {
                $staffelIds = array_map('intval', $_POST['staffeln']);
            }

            if ($wertungId <= 0) {
                $this->message = "Bitte wählen Sie eine Wertung aus.";
            } elseif ($this->model->setStaffelnForWertung($wertungId, $staffelIds)) {
                if (session_status() == PHP_SESSION_NONE) {
                    require_once __DIR__ . '/../CookieMonster.php';
                    startSecureSession();
                }
                $count = count($staffelIds);
                $_SESSION['success_message'] = $count === 1
                    ? "1 Staffel der Wertung zugeordnet."
                    : "$count Staffeln der Wertung zugeordnet.";
                header("Location: " . $this->redirectUrl . "?view=assign");
                exit;
            } else {
                $this->message = "Fehler beim Speichern der Zuordnung.";
            }
        }

        // Löschen einer Staffel
        if (isset($_POST['delete_Staffel'])) {
            $deleteNr = intval($_POST['delete_ID']);
            if ($this->model->delete($deleteNr)) {
                header("Location: " . $this->redirectUrl);
                exit;
            } else {
                $this->message = "Fehler beim Löschen der Staffel.";
            }
        }
        // Hinzufügen oder Aktualisieren einer Staffel
        if (isset($_POST['add_Staffel'])) {
            $name = trim($_POST['name']);

            // Prüfe, ob eine Staffel mit diesem Namen bereits existiert.
            if ($this->model->existsByName($name)) {
                $this->modalData = ['duplicate' => true];
            } else {
                $result = $this->model->create(['name' => $name]);
                if ($result !== false) {
                    header("Location: " . $this->redirectUrl);
                    exit;
                } else {
                    $this->message = 'Fehler beim Hinzufügen der Staffel.';
                }
            }
        }
    }

    /**
     * AJAX: Liefert alle Staffeln plus die einer Wertung bereits zugeordneten IDs
     * als JSON. Wird über StaffelInputView.php?action=... aufgerufen, sodass das
     * Rechte-Gate der View vorher greift.
     */
    private function handleAjaxGetStaffelnForWertung(): void
    {
        header('Content-Type: application/json');

        $wertungId = intval($_GET['wertung'] ?? 0);
        if ($wertungId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Keine Wertung angegeben']);
            exit;
        }

        $alleStaffeln = $this->model->read();
        $zugeordneteIds = $this->model->getStaffelnByWertung($wertungId);

        echo json_encode([
            'success'        => true,
            'alleStaffeln'   => $alleStaffeln,
            'zugeordneteIds' => $zugeordneteIds,
        ]);
        exit;
    }
}
