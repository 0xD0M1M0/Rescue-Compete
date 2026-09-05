<?php

namespace Station;

// DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PDO;
use PDOException;

/**
 * Klasse zur Verwaltung von Benutzer-Einträgen in der Datenbank.
 */
class UserModel
{
    private PDO $db;

    /**
     * Konstruktor.
     *
     * @param PDO $db Die PDO-Datenbankverbindung.
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Erstellt einen neuen Benutzer in der Datenbank.
     *
     * @param array $entry Enthält 'username', 'passwort' und 'acc_typ'.
     * @return int|null Die ID des neuen Nutzers oder null bei Fehler.
     */
    public function create(array $entry): ?int
    {
        try {
            // Überprüfen, ob der Nutzer bereits existiert
            $check = $this->db->prepare(
                "SELECT COUNT(*) 
                 FROM User 
                 WHERE username = :username"
            );
            $check->bindParam(':username', $entry['username']);
            $check->execute();

            if ($check->fetchColumn() > 0) {
                return null; // Nutzer existiert bereits
            }

            // Sicherheitsprüfung: Verhindere Admin-Erstellung durch Nicht-Admins
            if ($entry['acc_typ'] === 'Admin' &&
                (!isset($_SESSION['acc_typ']) || $_SESSION['acc_typ'] !== 'Admin')) {
                error_log("Unauthorized attempt to create admin account by user: " . ($_SESSION['username'] ?? 'unknown'));
                return null;
            }

            // Mannschaft_ID und station_ID korrekt verarbeiten
            $mannschaft = !empty($entry['mannschaft_ID']) ? $entry['mannschaft_ID'] : null;
            $stationID = !empty($entry['station_ID']) ? $entry['station_ID'] : null;
            $passwordHash = array_key_exists('passwordHash', $entry) ? $entry['passwordHash'] : null;
            $ssoEmail = !empty($entry['sso_email']) ? strtolower(trim($entry['sso_email'])) : null;
            $oidcSub = !empty($entry['oidc_sub']) ? $entry['oidc_sub'] : null;

            if ($ssoEmail !== null && $this->isSsoEmailTaken($ssoEmail)) {
                error_log("SSO-E-Mail bereits vergeben: " . $ssoEmail);
                return null;
            }

            // Nutzer erstellen
            $stmt = $this->db->prepare(
                "INSERT INTO User (username, passwordHash, acc_typ, mannschaft_ID, station_ID, oidc_sub, sso_email) 
                 VALUES (:username, :passwordHash, :acc_typ, :mannschaft_ID, :station_ID, :oidc_sub, :sso_email)"
            );
            $stmt->execute([
                ':username' => $entry['username'],
                ':passwordHash' => $passwordHash,
                ':acc_typ' => $entry['acc_typ'],
                ':mannschaft_ID' => $mannschaft,
                ':station_ID' => $stationID,
                ':oidc_sub' => $oidcSub,
                ':sso_email' => $ssoEmail,
            ]);
            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Fehler in User::create: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Liest Benutzer-Einträge aus der Datenbank.
     *
     * @param int|null $id Optional: ID eines spezifischen Eintrags.
     * @return array|null Ein oder mehrere Benutzer-Einträge oder null bei Fehler.
     */
    public function read(int $id = null): ?array
    {
        try {
            if ($id !== null) {
                $stmt = $this->db->prepare("SELECT * FROM User WHERE User.ID = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } else {
                $query = "SELECT User.ID, User.username, User.passwordHash, User.acc_typ, User.oidc_sub, User.sso_email, Mannschaft.Teamname 
                  FROM User 
                  LEFT JOIN Mannschaft ON User.mannschaft_ID = Mannschaft.ID";

                $stmt = $this->db->query($query);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Error in User::read: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Liest alle Benutzer außer Admin-Accounts aus der Datenbank.
     *
     * @return array|null Benutzer-Einträge ohne Admin-Accounts oder null bei Fehler.
     */
    public function readNonAdminUsers(): ?array
    {
        try {
            $query = "SELECT User.ID, User.username, User.passwordHash, User.acc_typ, User.oidc_sub, User.sso_email, Mannschaft.Teamname 
                      FROM User 
                      LEFT JOIN Mannschaft ON User.mannschaft_ID = Mannschaft.ID 
                      WHERE User.acc_typ != 'Admin'";

            $stmt = $this->db->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in User::readNonAdminUsers: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Liest nur Admin-Accounts aus der Datenbank.
     *
     * @return array|null Admin-Benutzer-Einträge oder null bei Fehler.
     */
    public function readAdminUsers(): ?array
    {
        try {
            $query = "SELECT User.ID, User.username, User.passwordHash, User.acc_typ, User.oidc_sub, User.sso_email, Mannschaft.Teamname 
                      FROM User 
                      LEFT JOIN Mannschaft ON User.mannschaft_ID = Mannschaft.ID 
                      WHERE User.acc_typ = 'Admin'";

            $stmt = $this->db->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in User::readAdminUsers: " . $e->getMessage());
            return null;
        }
    }

    public function bootlegRead(string $name): ?array
    {
        try {
            $statement = "SELECT ID, username, passwordHash, acc_typ, oidc_sub, sso_email FROM User WHERE username = :name";
            $query = $this->db->prepare($statement);
            $query->bindParam(':name', $name);
            $query->execute();
            $result = $query->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error in User::bootlegRead: " . $e->getMessage());
            return null;
        }
    }

    public function findByOidcSub(string $oidcSub): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM User WHERE oidc_sub = :oidc_sub LIMIT 1");
            $stmt->execute([':oidc_sub' => $oidcSub]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error in User::findByOidcSub: " . $e->getMessage());
            return null;
        }
    }

    public function findBySsoEmail(string $email): ?array
    {
        try {
            $normalized = strtolower(trim($email));
            $stmt = $this->db->prepare(
                "SELECT * FROM User
                 WHERE LOWER(sso_email) = :email
                 ORDER BY CASE WHEN acc_typ = 'Admin' THEN 0 ELSE 1 END, ID ASC
                 LIMIT 1"
            );
            $stmt->execute([':email' => $normalized]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error in User::findBySsoEmail: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Prüft, ob die SSO-E-Mail bereits einem anderen Benutzer gehört.
     */
    public function isSsoEmailTaken(string $email, ?int $excludeUserId = null): bool
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return false;
        }

        try {
            if ($excludeUserId !== null) {
                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) FROM User
                     WHERE LOWER(sso_email) = :email AND ID != :id"
                );
                $stmt->execute([
                    ':email' => $normalized,
                    ':id' => $excludeUserId,
                ]);
            } else {
                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) FROM User WHERE LOWER(sso_email) = :email"
                );
                $stmt->execute([':email' => $normalized]);
            }

            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error in User::isSsoEmailTaken: " . $e->getMessage());
            return true;
        }
    }

    public function linkOidcSub(int $id, string $oidcSub): bool
    {
        try {
            // oidc_sub ist UNIQUE — ggf. von anderem Account lösen (z. B. nach Admin-Bevorzugung)
            $clear = $this->db->prepare(
                "UPDATE User SET oidc_sub = NULL WHERE oidc_sub = :oidc_sub AND ID != :id"
            );
            $clear->execute([
                ':oidc_sub' => $oidcSub,
                ':id' => $id,
            ]);

            $stmt = $this->db->prepare("UPDATE User SET oidc_sub = :oidc_sub WHERE ID = :id");
            return $stmt->execute([
                ':oidc_sub' => $oidcSub,
                ':id' => $id,
            ]);
        } catch (PDOException $e) {
            error_log("Error in User::linkOidcSub: " . $e->getMessage());
            return false;
        }
    }

    public function updateAccTyp(int $id, string $accTyp): bool
    {
        try {
            if ($accTyp === 'Admin' &&
                (!isset($_SESSION['acc_typ']) || $_SESSION['acc_typ'] !== 'Admin')) {
                return false;
            }

            $stmt = $this->db->prepare("UPDATE User SET acc_typ = :acc_typ WHERE ID = :id");
            return $stmt->execute([
                ':acc_typ' => $accTyp,
                ':id' => $id,
            ]) && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error in User::updateAccTyp: " . $e->getMessage());
            return false;
        }
    }

    public function updateSsoEmail(int $id, ?string $ssoEmail): bool
    {
        try {
            $normalized = $ssoEmail !== null && $ssoEmail !== ''
                ? strtolower(trim($ssoEmail))
                : null;

            if ($normalized !== null && $this->isSsoEmailTaken($normalized, $id)) {
                return false;
            }

            $stmt = $this->db->prepare("UPDATE User SET sso_email = :sso_email WHERE ID = :id");
            return $stmt->execute([
                ':sso_email' => $normalized,
                ':id' => $id,
            ]);
        } catch (PDOException $e) {
            error_log("Error in User::updateSsoEmail: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Legt einen SSO-Benutzer ohne Rolle an (Wartend).
     */
    public function createFromSso(string $oidcSub, string $email, ?string $preferredUsername = null): ?int
    {
        $base = $preferredUsername ?: strstr($email, '@', true) ?: $email;
        $base = preg_replace('/[^a-zA-Z0-9._-]/', '', $base) ?: 'user';
        $base = substr($base, 0, 28);

        $username = $base;
        $suffix = 1;
        while ($this->bootlegRead($username) !== null) {
            $username = substr($base, 0, 28) . $suffix;
            $suffix++;
            if ($suffix > 99) {
                return null;
            }
        }

        return $this->create([
            'username' => $username,
            'passwordHash' => null,
            'acc_typ' => 'Wartend',
            'mannschaft_ID' => null,
            'station_ID' => null,
            'oidc_sub' => $oidcSub,
            'sso_email' => strtolower(trim($email)),
        ]);
    }

    /**
     * Löscht einen Benutzer-Eintrag aus der Datenbank.
     *
     * @param int $id ID des zu löschenden Benutzers.
     * @return bool True bei Erfolg, false bei Fehler.
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM User WHERE ID = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in User::delete: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Aktualisiert nur das Passwort eines Benutzers.
     *
     * @param int $id Benutzer-ID
     * @param string $newPasswordHash Neuer Passwort-Hash
     * @return bool True bei Erfolg, false bei Fehler
     */
    public function updatePassword(int $id, string $newPasswordHash): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE User SET passwordHash = :passwordHash WHERE ID = :id");
            $stmt->bindParam(':passwordHash', $newPasswordHash);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error in User::updatePassword: " . $e->getMessage());
            return false;
        }
    }

    public function addOrUpdateUser(array $entry, bool $confirmUpdate = false, $providedDuplicateId = null): array
    {
        try {
            // Falls der Benutzer bereits im Modal bestätigt hat, dass Duplikat zu aktualisieren
            if ($confirmUpdate && $providedDuplicateId !== null) {
                $updated = $this->updateUser($providedDuplicateId, $entry);
                if ($updated) {
                    return ['status' => 'updated', 'message' => 'Benutzer wurde erfolgreich aktualisiert.'];
                } else {
                    return ['status' => 'error', 'message' => 'Fehler beim Aktualisieren des Benutzers.'];
                }
            }

            // Prüfe auf Duplikate
            $query = "SELECT * FROM User WHERE username = :username";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':username' => $entry['username'],
            ]);
            $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $foundName = null;
            foreach ($duplicates as $dup) {
                $matchName = (strcasecmp($dup['username'], $entry['username']) === 0);
                if ($matchName && $foundName === null) {
                    $foundName = $dup;
                }
            }

            // Falls ein Duplikat gefunden wurde und noch nicht bestätigt wurde, wird dies zurückgegeben
            if (!$confirmUpdate) {
                if ($foundName) {
                    return [
                        'status' => 'duplicate',
                        'message' => "Ein Benutzer mit dem Namen '{$entry['username']}' existiert bereits. Möchten Sie ihn aktualisieren?",
                        'duplicate_id' => $foundName['ID']
                    ];
                }
            }

            // Falls kein Duplikat vorliegt, neuen Datensatz anlegen
            $newId = $this->create($entry);
            if ($newId) {
                return ['status' => 'created', 'message' => 'Neuer Benutzer wurde erfolgreich erstellt.', 'new_id' => $newId];
            } else {
                return ['status' => 'error', 'message' => 'Fehler beim Erstellen des Benutzers. Möglicherweise existiert der Benutzername bereits.'];
            }
        } catch (PDOException $e) {
            error_log("Error in addOrUpdateUser: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Ein Datenbankfehler ist aufgetreten. Bitte versuchen Sie es erneut.'];
        }
    }

    public function updateUser(int $id, array $entry): bool
    {
        try {
            // Mannschaft_ID und station_ID korrekt verarbeiten
            $mannschaft = !empty($entry['mannschaft_ID']) ? $entry['mannschaft_ID'] : null;
            $stationID = !empty($entry['station_ID']) ? $entry['station_ID'] : null;
            $ssoEmail = array_key_exists('sso_email', $entry) && $entry['sso_email'] !== null && $entry['sso_email'] !== ''
                ? strtolower(trim($entry['sso_email']))
                : null;

            if ($ssoEmail !== null && $this->isSsoEmailTaken($ssoEmail, $id)) {
                error_log("SSO-E-Mail bereits vergeben bei Update: " . $ssoEmail);
                return false;
            }

            // Passwort nur überschreiben, wenn ein neuer Hash mitgegeben wurde
            if (array_key_exists('passwordHash', $entry) && $entry['passwordHash'] !== null && $entry['passwordHash'] !== '') {
                $queryUpdate = "UPDATE User SET username = :username, passwordHash = :passwordHash, acc_typ = :acc_typ, 
                                    mannschaft_ID = :mannschaft_ID, station_ID = :station_ID, sso_email = :sso_email WHERE ID = :id";
                $stmtUpdate = $this->db->prepare($queryUpdate);
                return $stmtUpdate->execute([
                    ':username' => $entry['username'],
                    ':passwordHash' => $entry['passwordHash'],
                    ':acc_typ' => $entry['acc_typ'],
                    ':mannschaft_ID' => $mannschaft,
                    ':station_ID' => $stationID,
                    ':sso_email' => $ssoEmail,
                    ':id' => $id
                ]);
            }

            $queryUpdate = "UPDATE User SET username = :username, acc_typ = :acc_typ, 
                                mannschaft_ID = :mannschaft_ID, station_ID = :station_ID, sso_email = :sso_email WHERE ID = :id";
            $stmtUpdate = $this->db->prepare($queryUpdate);
            return $stmtUpdate->execute([
                ':username' => $entry['username'],
                ':acc_typ' => $entry['acc_typ'],
                ':mannschaft_ID' => $mannschaft,
                ':station_ID' => $stationID,
                ':sso_email' => $ssoEmail,
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("Error in updateUser: " . $e->getMessage());
            return false;
        }
    }
}