<?php

namespace Station\Controller;

use Mannschaft\TeamModel;

class MannschaftController {
    private TeamModel $model;
    public string $message = "";
    public array $duplicateData = [];
    public array $errorData = [];
    public array $successData = [];
    public string $redirectUrl = "TeamInputView.php";

    public function __construct(TeamModel $model) {
        $this->model = $model;
    }

    public function handleRequest() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
            enforce_post_same_origin();
        }

        if (isset($_POST['delete_team'])) {
            $this->handleDeleteRequest();
        }

        if (isset($_POST['add_team'])) {
            $this->handleAddOrUpdateRequest();
        }
    }

    private function handleDeleteRequest() {
        $deleteId = intval($_POST['delete_id'] ?? 0);

        if ($deleteId <= 0) {
            $this->errorData = [
                'title' => 'Ungültige ID',
                'message' => 'Die Mannschafts-ID ist ungültig.'
            ];
            return;
        }

        if (!$this->model->teamExists($deleteId)) {
            $this->errorData = [
                'title' => 'Mannschaft nicht gefunden',
                'message' => 'Die zu löschende Mannschaft wurde nicht gefunden.'
            ];
            return;
        }

        if ($this->model->delete($deleteId)) {
            header("Location: " . $this->redirectUrl . "?view=overview&deleted=1");
            exit;
        } else {
            $this->errorData = [
                'title' => 'Löschfehler',
                'message' => 'Fehler beim Löschen der Mannschaft. Bitte versuchen Sie es erneut.'
            ];
        }
    }

    private function handleAddOrUpdateRequest() {
        $teamname = $this->sanitizeInput($_POST['teamname'] ?? '');
        $kreisverband = $this->sanitizeInput($_POST['kreisverband'] ?? '');
        $landesverband = $this->sanitizeInput($_POST['landesverband'] ?? '');

        $validationErrors = $this->validateTeamData($teamname, $kreisverband, $landesverband);
        if (!empty($validationErrors)) {
            $this->errorData = [
                'title' => 'Eingabefehler',
                'message' => implode(' ', $validationErrors)
            ];
            return;
        }

        $confirmUpdate = isset($_POST['confirm_update']) && $_POST['confirm_update'] == "1";
        $providedDuplicateId = isset($_POST['duplicate_id']) ? intval($_POST['duplicate_id']) : null;

        $entry = [
            'Teamname'      => $teamname,
            'Kreisverband'  => $kreisverband,
            'Landesverband' => $landesverband
        ];

        $result = $this->model->addOrUpdateTeam($entry, $confirmUpdate, $providedDuplicateId);

        $this->handleAddOrUpdateResult($result, $teamname, $kreisverband, $landesverband);
    }

    private function sanitizeInput(string $input): string {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }

    private function validateTeamData(string $teamname, string $kreisverband, string $landesverband): array {
        $errors = [];

        if (empty($teamname)) {
            $errors[] = "Der Teamname darf nicht leer sein.";
        } elseif (strlen($teamname) > 100) {
            $errors[] = "Der Teamname darf maximal 100 Zeichen lang sein.";
        }

        if (empty($kreisverband)) {
            $errors[] = "Der Kreisverband darf nicht leer sein.";
        } elseif (strlen($kreisverband) > 32) {
            $errors[] = "Der Kreisverband darf maximal 32 Zeichen lang sein.";
        }

        if (empty($landesverband)) {
            $errors[] = "Der Landesverband darf nicht leer sein.";
        } elseif (strlen($landesverband) > 32) {
            $errors[] = "Der Landesverband darf maximal 32 Zeichen lang sein.";
        }

        return $errors;
    }

    private function handleAddOrUpdateResult(array $result, string $teamname, string $kreisverband, string $landesverband) {
        switch ($result['status']) {
            case 'duplicate':
                $existingData = $result['existing_data'];
                $this->duplicateData = [
                    'duplicate_id' => $result['duplicate_id'],
                    'teamname'     => $teamname,
                    'kreisverband' => $kreisverband,
                    'landesverband'=> $landesverband,
                    'existing_kreisverband' => $existingData['Kreisverband'],
                    'existing_landesverband' => $existingData['Landesverband']
                ];
                break;

            case 'created':
                header("Location: " . $this->redirectUrl . "?view=create&created=1");
                exit;

            case 'updated':
                header("Location: " . $this->redirectUrl . "?view=create&updated=1");
                exit;

            case 'error':
            default:
                $this->errorData = [
                    'title' => 'Fehler beim Speichern',
                    'message' => $result['message'] ?? 'Ein unbekannter Fehler ist aufgetreten.'
                ];
                break;
        }
    }
}