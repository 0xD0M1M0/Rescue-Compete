<?php

namespace FormRedirect;

require_once '../db/DbConnection.php';
require_once '../model/FormCollectionModel.php';
require_once '../model/TeamModel.php';

use PDO;
use PDOException;
use FormCollection\FormCollectionModel;
use Mannschaft\TeamModel;

/**
 * Controller für QR-Code-Weiterleitung im FormCollection-System
 * Verarbeitet QR-Code-Scans und leitet zu entsprechenden FormCollection-Instanzen weiter
 */
class QrRedirectController
{
    private PDO $db;
    private FormCollectionModel $formCollectionModel;
    private TeamModel $mannschaftModel;

    public string $message = '';

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->formCollectionModel = new FormCollectionModel($db);
        $this->mannschaftModel = new TeamModel($db);
    }

    /**
     * Verarbeitet QR-Code-Token und leitet zur entsprechenden FormCollection-Instance weiter
     *
     * @param string $tokenCode QR-Code-Token
     * @param int $teamId ID des Teams
     * @return array|null Weiterleitung-Informationen oder null bei Fehler
     */
    public function processQrCodeRedirect(string $tokenCode, int $teamId): ?array
    {
        try {
            // Token im FormCollection-System auflösen
            $tokenInfo = $this->formCollectionModel->resolveFormToken($tokenCode);

            if (!$tokenInfo) {
                $this->message = "Ungültiger QR-Code. Das Formular wurde nicht gefunden.";
                return null;
            }

            // TeamFormInstance on-demand erstellen oder holen
            $instanceResult = $this->formCollectionModel->createTeamFormInstanceOnDemand(
                $teamId,
                $tokenInfo['collection_ID'],
                $tokenInfo['formNumber']
            );

            if ($instanceResult && isset($instanceResult['token'])) {
                return [
                    'success' => true,
                    'redirectUrl' => '/view/FormView.php?token=' . $instanceResult['token'],
                    'collectionName' => $tokenInfo['collectionName'],
                    'formNumber' => $tokenInfo['formNumber'],
                    'instanceToken' => $instanceResult['token'],
                    'isNewInstance' => !($instanceResult['existing'] ?? false)
                ];
            } else {
                $this->message = "Fehler beim Erstellen des Formulars. Bitte versuche es erneut.";
                return null;
            }

        } catch (PDOException $e) {
            error_log("Datenbankfehler:" . $e->getMessage());
            $this->message = "Ein Fehler ist aufgetreten.";
            error_log("Error in QrRedirectController::processQrCodeRedirect: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Holt die Team-ID für einen Benutzer
     *
     * @param int $userId ID des Benutzers
     * @return int|null Team-ID oder null wenn nicht gefunden
     */
    public function getTeamIdForUser(int $userId): ?int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT mannschaft_ID 
                 FROM User 
                 WHERE ID = :userId"
            );
            $stmt->execute([':userId' => $userId]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['mannschaft_ID']) {
                return (int)$result['mannschaft_ID'];
            }

            $this->message = "Benutzer ist keinem Team zugeordnet.";
            return null;

        } catch (PDOException $e) {
            error_log("Datenbankfehler:" . $e->getMessage());
            $this->message = "Ein Fehler ist aufgetreten.";
            error_log("Error in QrRedirectController::getTeamIdForUser: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validiert einen QR-Code-Token
     *
     * @param string $tokenCode Token-Code
     * @return bool True wenn gültig, false wenn nicht
     */
    public function validateQrToken(string $tokenCode): bool
    {
        try {
            $tokenInfo = $this->formCollectionModel->resolveFormToken($tokenCode);
            return $tokenInfo !== null;
        } catch (PDOException $e) {
            error_log("Error in QrRedirectController::validateQrToken: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Holt Informationen über einen QR-Code ohne Instance zu erstellen
     *
     * @param string $tokenCode Token-Code
     * @return array|null Token-Informationen oder null bei Fehler
     */
    public function getQrTokenInfo(string $tokenCode): ?array
    {
        try {
            return $this->formCollectionModel->resolveFormToken($tokenCode);
        } catch (PDOException $e) {
            error_log("Error in QrRedirectController::getQrTokenInfo: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generiert QR-Code-URLs für alle Collections (Admin-Funktion)
     *
     * @param string $baseUrl Basis-URL (optional)
     * @return array Array mit Collection-QR-Code-Daten
     */
    public function getAllCollectionQrCodes(string $baseUrl = ''): array
    {
        try {
            if (empty($baseUrl)) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $baseUrl = $protocol . '://' . $host;
            }

            $stmt = $this->db->query(
                "SELECT cft.*, fc.name as collectionName
                 FROM CollectionFormToken cft
                 JOIN FormCollection fc ON cft.collection_ID = fc.ID
                 ORDER BY fc.name, cft.formNumber"
            );
            $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // QR-Code-URLs hinzufügen
            foreach ($tokens as &$token) {
                $token['qrCodeUrl'] = $baseUrl . '/view/FormRedirect.php?code=' . $token['token'];
            }

            return $tokens;

        } catch (PDOException $e) {
            error_log("Error in QrRedirectController::getAllCollectionQrCodes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Prüft ob ein Team bereits eine Instance für eine Collection hat
     *
     * @param int $teamId Team-ID
     * @param int $collectionId Collection-ID
     * @param int $formNumber Formular-Nummer
     * @return array|null Bestehende Instance-Daten oder null
     */
    public function checkExistingInstance(int $teamId, int $collectionId, int $formNumber): ?array
    {
        try {
            return $this->formCollectionModel->getTeamFormInstance($teamId, $collectionId, $formNumber);
        } catch (PDOException $e) {
            error_log("Error in QrRedirectController::checkExistingInstance: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Holt Team-Informationen
     *
     * @param int $teamId Team-ID
     * @return array|null Team-Daten oder null
     */
    public function getTeamInfo(int $teamId): ?array
    {
        try {
            return $this->mannschaftModel->read($teamId);
        } catch (PDOException $e) {
            error_log("Error in QrRedirectController::getTeamInfo: " . $e->getMessage());
            return null;
        }
    }
}