<?php

namespace FormCollection;

use PDO;
use PDOException;

/**
 * Model-Klasse für die Verwaltung von CollectionFormTokens
 * Verwaltet QR-Code-Tokens für FormCollection-Formulare
 */
class CollectionFormTokenModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Erstellt einen neuen Token für eine Collection und Formularnummer
     *
     * @param int $collectionId ID der FormCollection
     * @param int $formNumber Nummer des Formulars (1, 2, 3, etc.)
     * @return string|null Generierter Token oder null bei Fehler
     */
    public function createToken(int $collectionId, int $formNumber): ?string
    {
        try {
            // Prüfen ob Token bereits existiert
            $existingToken = $this->getTokenByCollectionAndForm($collectionId, $formNumber);
            if ($existingToken) {
                return $existingToken['token'];
            }

            // Neuen eindeutigen Token generieren
            $token = $this->generateUniqueToken($collectionId, $formNumber);

            $stmt = $this->db->prepare(
                "INSERT INTO CollectionFormToken (collection_ID, formNumber, token) 
                 VALUES (:collectionId, :formNumber, :token)"
            );

            $stmt->execute([
                ':collectionId' => $collectionId,
                ':formNumber' => $formNumber,
                ':token' => $token
            ]);

            return $token;
        } catch (PDOException $e) {
            error_log("Error in CollectionFormTokenModel::createToken: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Liest einen oder mehrere Tokens aus der Datenbank
     *
     * @param int|null $tokenId Optional: ID eines spezifischen Tokens
     * @return array|null Token-Daten oder null bei Fehler
     */
    public function readToken(?int $tokenId = null): ?array
    {
        try {
            if ($tokenId === null) {
                // Alle Tokens mit Collection-Informationen
                $stmt = $this->db->query(
                    "SELECT cft.*, fc.name as collectionName, fc.timeLimit
                     FROM CollectionFormToken cft
                     JOIN FormCollection fc ON cft.collection_ID = fc.ID
                     ORDER BY fc.name, cft.formNumber"
                );
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Spezifischer Token
                $stmt = $this->db->prepare(
                    "SELECT cft.*, fc.name as collectionName, fc.timeLimit
                     FROM CollectionFormToken cft
                     JOIN FormCollection fc ON cft.collection_ID = fc.ID
                     WHERE cft.ID = :tokenId"
                );
                $stmt->execute([':tokenId' => $tokenId]);
                return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        } catch (PDOException $e) {
            error_log("Error in CollectionFormTokenModel::readToken: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Aktualisiert einen bestehenden Token
     *
     * @param int $tokenId ID des zu aktualisierenden Tokens
     * @param array $tokenData Neue Token-Daten
     * @return bool True bei Erfolg, false bei Fehler
     */
    public function updateToken(int $tokenId, array $tokenData): bool
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE CollectionFormToken 
                 SET collection_ID = :collectionId, formNumber = :formNumber, token = :token
                 WHERE ID = :tokenId"
            );

            return $stmt->execute([
                ':collectionId' => $tokenData['collection_ID'],
                ':formNumber' => $tokenData['formNumber'],
                ':token' => $tokenData['token'],
                ':tokenId' => $tokenId
            ]);
        } catch (PDOException $e) {
            error_log("Error in CollectionFormTokenModel::updateToken: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Löscht einen Token aus der Datenbank
     *
     * @param int $tokenId ID des zu löschenden Tokens
     * @return bool True bei Erfolg, false bei Fehler
     */
    public function deleteToken(int $tokenId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM CollectionFormToken WHERE ID = :tokenId");
            return $stmt->execute([':tokenId' => $tokenId]);
        } catch (PDOException $e) {
            error_log("Error in CollectionFormTokenModel::deleteToken: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Holt alle Tokens für eine bestimmte Collection
     *
     * @param int $collectionId ID der FormCollection
     * @return array Array mit Token-Daten
     */
    public function getTokensByCollection(int $collectionId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT cft.*, fc.name as collectionName
                 FROM CollectionFormToken cft
                 JOIN FormCollection fc ON cft.collection_ID = fc.ID
                 WHERE cft.collection_ID = :collectionId
                 ORDER BY cft.formNumber"
            );
            $stmt->execute([':collectionId' => $collectionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in CollectionFormTokenModel::getTokensByCollection: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Holt Token anhand der Collection und Formularnummer
     *
     * @param int $collectionId ID der FormCollection
     * @param int $formNumber Nummer des Formulars
     * @return array|null Token-Daten oder null wenn nicht gefunden
     */
    public function getTokenByCollectionAndForm(int $collectionId, int $formNumber): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT cft.*, fc.name as collectionName
                 FROM CollectionFormToken cft
                 JOIN FormCollection fc ON cft.collection_ID = fc.ID
                 WHERE cft.collection_ID = :collectionId AND cft.formNumber = :formNumber"
            );
            $stmt->execute([
                ':collectionId' => $collectionId,
                ':formNumber' => $formNumber
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error in CollectionFormTokenModel::getTokenByCollectionAndForm: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Löst einen Token-String auf und gibt Collection-Informationen zurück
     *
     * @param string $tokenString Token-String aus dem QR-Code
     * @return array|null Token-Informationen oder null wenn nicht gefunden
     */
    public function resolveToken(string $tokenString): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT cft.collection_ID, cft.formNumber, fc.name as collectionName, 
                        fc.timeLimit, fc.totalQuestions, fc.formsCount,
                        s.name as stationName
                 FROM CollectionFormToken cft
                 JOIN FormCollection fc ON cft.collection_ID = fc.ID
                 LEFT JOIN Station s ON fc.station_ID = s.ID
                 WHERE cft.token = :token"
            );
            $stmt->execute([':token' => $tokenString]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error in CollectionFormTokenModel::resolveToken: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Prüft ob ein Token-String bereits existiert
     *
     * @param string $tokenString Zu prüfender Token
     * @return bool True wenn Token existiert, false wenn nicht
     */
    public function tokenExists(string $tokenString): bool
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM CollectionFormToken WHERE token = :token");
            $stmt->execute([':token' => $tokenString]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error in CollectionFormTokenModel::tokenExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generiert alle Tokens für eine Collection neu
     *
     * @param int $collectionId ID der FormCollection
     * @param int $formsCount Anzahl der Formulare
     * @return bool True bei Erfolg, false bei Fehler
     */
    public function regenerateTokensForCollection(int $collectionId, int $formsCount): bool
    {
        try {
            $this->db->beginTransaction();

            // Alle bestehenden Tokens löschen
            $stmt = $this->db->prepare("DELETE FROM CollectionFormToken WHERE collection_ID = :collectionId");
            $stmt->execute([':collectionId' => $collectionId]);

            // Neue Tokens für jedes Formular generieren
            for ($formNumber = 1; $formNumber <= $formsCount; $formNumber++) {
                $token = $this->createToken($collectionId, $formNumber);
                if (!$token) {
                    throw new PDOException("Failed to create token for form {$formNumber}");
                }
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error in CollectionFormTokenModel::regenerateTokensForCollection: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Löscht alle Tokens für eine Collection
     *
     * @param int $collectionId ID der FormCollection
     * @return bool True bei Erfolg, false bei Fehler
     */
    public function deleteTokensByCollection(int $collectionId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM CollectionFormToken WHERE collection_ID = :collectionId");
            return $stmt->execute([':collectionId' => $collectionId]);
        } catch (PDOException $e) {
            error_log("Error in CollectionFormTokenModel::deleteTokensByCollection: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generiert QR-Code-URLs für alle Tokens einer Collection
     *
     * @param int $collectionId ID der FormCollection
     * @param string $baseUrl Basis-URL für QR-Codes (optional)
     * @return array Array mit Token-Daten und URLs
     */
    public function getTokensWithUrls(int $collectionId, string $baseUrl = ''): array
    {
        try {
            $tokens = $this->getTokensByCollection($collectionId);

            // Standard-URL aus Server-Umgebung wenn nicht angegeben
            if (empty($baseUrl)) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $baseUrl = $protocol . '://' . $host;
            }

            // QR-Code-URLs hinzufügen
            foreach ($tokens as &$token) {
                $token['qrCodeUrl'] = $baseUrl . '/view/FormRedirect.php?code=' . $token['token'];
            }

            return $tokens;
        } catch (PDOException $e) {
            error_log("Error in CollectionFormTokenModel::getTokensWithUrls: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generiert einen eindeutigen Token
     *
     * @param int $collectionId ID der Collection
     * @param int $formNumber Formularnummer
     * @return string 12-stelliger eindeutiger Token
     */
    private function generateUniqueToken(int $collectionId, int $formNumber): string
    {
        do {
            $token = bin2hex(random_bytes(16));
        } while ($this->tokenExists($token));

        return $token;
    }

    /**
     * Holt Statistiken über Token-Nutzung
     *
     * @param int|null $collectionId Optional: Spezifische Collection
     * @return array Token-Nutzungsstatistiken
     */
    public function getTokenUsageStats(?int $collectionId = null): array
    {
        try {
            $whereClause = $collectionId ? "WHERE cft.collection_ID = :collectionId" : "";

            $stmt = $this->db->prepare(
                "SELECT 
                    cft.collection_ID,
                    fc.name as collectionName,
                    cft.formNumber,
                    cft.token,
                    COUNT(tfi.ID) as usageCount,
                    COUNT(CASE WHEN tfi.completed = 1 THEN 1 END) as completedCount,
                    MAX(tfi.startTime) as lastUsed
                 FROM CollectionFormToken cft
                 JOIN FormCollection fc ON cft.collection_ID = fc.ID
                 LEFT JOIN TeamFormInstance tfi ON cft.collection_ID = tfi.collection_ID 
                                                AND cft.formNumber = tfi.formNumber
                 {$whereClause}
                 GROUP BY cft.ID, cft.collection_ID, fc.name, cft.formNumber, cft.token
                 ORDER BY fc.name, cft.formNumber"
            );

            if ($collectionId) {
                $stmt->execute([':collectionId' => $collectionId]);
            } else {
                $stmt->execute();
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in CollectionFormTokenModel::getTokenUsageStats: " . $e->getMessage());
            return [];
        }
    }
}
