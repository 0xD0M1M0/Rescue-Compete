<?php

namespace Protocol;

class ProtocolInputController
{
    private ProtocolModel $model;
    public string $message = "";
    public array $modalData = [];
    public string $redirectUrl = "ProtocolInputView.php";

    /**
     * Initialisiert den Controller mit dem ProtocolModel-Modell.
     *
     * @param ProtocolModel $model Das Modell zur Verwaltung der Protokolle.
     */
    public function __construct(ProtocolModel $model)
    {
        $this->model = $model;
    }

    /**
     * Verarbeitet den Request zum Löschen, Hinzufügen oder Aktualisieren von Protokollen.
     */
    public function handleRequest()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
            enforce_post_same_origin();
        }

        // Löschen eines Protokolls
        if (isset($_POST['delete_protocol'])) {
            $deleteId = intval($_POST['delete_Nr']);
            if ($this->model->delete($deleteId)) {
                header("Location: " . $this->redirectUrl);
                exit;
            } else {
                $this->message = "Fehler beim Löschen des Protokolls.";
            }
        }

        // Hinzufügen oder Aktualisieren von Protokollen
        if (isset($_POST['add_protocol'])) {
            // Gemeinsamer Stationswert aus dem Dropdown oder dem Hidden-Feld
            $stationInput = trim($_POST['stationName'] ?? $_POST['station_ID'] ?? '');

            if (empty($stationInput)) {
                $this->message = "Keine Station ausgewählt. Bitte wählen Sie eine Station.";
                return;
            }
            $station_ID = $this->model->stationReverseRead($stationInput);

            $confirmUpdate = isset($_POST['confirm_update']) && $_POST['confirm_update'] == "1";
            $providedDuplicateId = isset($_POST['duplicate_Nr']) ? intval($_POST['duplicate_Nr']) : null;
            $redirect = true;

            // Sammle alle zu verarbeitenden Protokolle
            $protocolsToProcess = [];

            // Verarbeite den statischen Eintrag (Felder "Name" und "max_Punkte")
            if (!empty($_POST['Name']) && !empty($_POST['max_Punkte'])) {
                $protocolsToProcess[] = [
                    'Name'       => trim($_POST['Name']),
                    'max_Punkte' => trim($_POST['max_Punkte']),
                    'station_ID' => $station_ID
                ];
            }

            // Verarbeite dynamische Einträge (Array-Struktur aus JavaScript)
            if (isset($_POST['protocols']) && is_array($_POST['protocols'])) {
                foreach ($_POST['protocols'] as $index => $protocolData) {
                    // Die korrekten Feldnamen aus dem JavaScript verwenden
                    if (!empty($protocolData['name']) && !empty($protocolData['points'])) {
                        $protocolsToProcess[] = [
                            'Name'       => trim($protocolData['name']),
                            'max_Punkte' => trim($protocolData['points']),
                            'station_ID' => $station_ID
                        ];
                    }
                }
            }

            // Alle gesammelten Protokolle verarbeiten
            foreach ($protocolsToProcess as $entry) {
                $result = $this->model->addOrUpdateProtocol($entry, $confirmUpdate, $providedDuplicateId);

                if ($result['status'] === 'duplicate') {
                    // Das Model liefert jetzt den vollständigen Meldungstext.
                    $this->modalData = [
                        'message'      => $result['message'],
                        'duplicate_Nr' => $result['duplicate_Nr'],
                        'Name'         => $entry['Name'],
                        'max_Punkte'   => $entry['max_Punkte'],
                        'station_ID'   => $station_ID
                    ];
                    $redirect = false;
                    break; // Bei Duplikat stoppen und auf Benutzerbestätigung warten
                } elseif ($result['status'] === 'error') {
                    $this->message = $result['message'];
                    $redirect = false;
                    break; // Bei Fehler stoppen
                }
            }

            if ($redirect) {
                header("Location: " . $this->redirectUrl);
                exit;
            }
        }
    }
}