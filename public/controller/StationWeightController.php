<?php

namespace Station\Controller;

use Station\StationModel;
use Model\WeightDistribution;

require_once __DIR__ . '/../model/WeightDistribution.php';

/**
 * Controller für die Wertungs-abhängige Stationsgewichtung.
 *
 * Pro Wertung ergeben die zugeordneten Stationen zusammen exakt 100 % des
 * Parcours-Anteils. Die Gewichte werden in WertungStation.weight gespeichert.
 */
class StationWeightController {
    private StationModel $stationModel;
    public string $message = "";
    public string $redirectUrl = "StationWeightInputView.php";

    public function __construct(StationModel $stationModel) {
        $this->stationModel = $stationModel;
    }

    /**
     * Verarbeitet AJAX-Vorbelegung und das Speichern. Liefert die Daten für die View.
     *
     * @return array ['wertungen' => array, 'message' => string]
     */
    public function processRequest(): array {
        // AJAX: Stationen + wirksame Startgewichte einer Wertung (für die UI-Vorbelegung)
        if (isset($_GET['action']) && $_GET['action'] === 'getWeightsForWertung') {
            $this->handleAjaxGetWeightsForWertung();
            // exit erfolgt in der Methode
        }

        // Speichern der Gewichte einer Wertung
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_weights'])) {
            $this->handleSaveWeights();
        }

        return [
            'wertungen' => $this->stationModel->getAllWertungen(),
            'message'   => $this->message,
        ];
    }

    /**
     * AJAX: Liefert die einer Wertung zugeordneten Stationen samt wirksamem
     * Startgewicht (gespeicherte Werte falls Summe 100, sonst Gleichverteilung).
     */
    private function handleAjaxGetWeightsForWertung(): void {
        header('Content-Type: application/json');

        $wertungId = intval($_GET['wertung'] ?? 0);
        if ($wertungId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Keine Wertung angegeben']);
            exit;
        }

        $stationen = $this->stationModel->getWeightedStationsByWertung($wertungId);
        $effective = WeightDistribution::effective(array_column($stationen, 'weight'));

        $result = [];
        foreach ($stationen as $i => $station) {
            $result[] = [
                'ID'     => (int)$station['ID'],
                'name'   => $station['name'],
                'Nr'     => (int)$station['Nr'],
                'weight' => (int)($effective[$i] ?? 0),
            ];
        }

        echo json_encode(['success' => true, 'stationen' => $result]);
        exit;
    }

    /**
     * Validiert und speichert die Gewichte einer Wertung. Erlaubt nur ganzzahlige
     * Werte 0–100, die zusammen exakt 100 ergeben, und nur für genau die der Wertung
     * zugeordneten Stationen.
     */
    private function handleSaveWeights(): void {
        $wertungId = intval($_POST['wertung'] ?? 0);
        $weightsInput = (isset($_POST['weights']) && is_array($_POST['weights'])) ? $_POST['weights'] : [];

        if ($wertungId <= 0) {
            $this->message = "Bitte wählen Sie eine Wertung aus.";
            return;
        }

        // Erlaubte Stationen dieser Wertung
        $assignedIds = $this->stationModel->getStationsByWertung($wertungId);
        if (empty($assignedIds)) {
            $this->message = "Dieser Wertung sind keine Stationen zugeordnet.";
            return;
        }

        // Nur Gewichte zugeordneter Stationen übernehmen; jede zugeordnete Station muss vorkommen.
        $idToWeight = [];
        foreach ($assignedIds as $stationId) {
            if (!isset($weightsInput[$stationId]) || !is_numeric($weightsInput[$stationId])) {
                $this->message = "Unvollständige oder ungültige Gewichtungen.";
                return;
            }
            $w = (int)$weightsInput[$stationId];
            if ($w < 0 || $w > 100) {
                $this->message = "Gewichtungen müssen zwischen 0 und 100 liegen.";
                return;
            }
            $idToWeight[$stationId] = $w;
        }

        if (array_sum($idToWeight) !== 100) {
            $this->message = "Die Gewichtungen müssen zusammen exakt 100 % ergeben.";
            return;
        }

        if ($this->stationModel->setStationWeightsForWertung($wertungId, $idToWeight)) {
            if (session_status() == PHP_SESSION_NONE) {
                require_once __DIR__ . '/../CookieMonster.php';
                startSecureSession();
            }
            $_SESSION['success_message'] = "Gewichtungen erfolgreich gespeichert.";
            header("Location: " . $this->redirectUrl . "?wertung=" . $wertungId);
            exit;
        }

        $this->message = "Fehler beim Speichern der Gewichtungen.";
    }
}
