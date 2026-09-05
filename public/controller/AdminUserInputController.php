<?php
namespace AdminUser;

use Station\UserModel;

require_once __DIR__ . '/../auth/PasswordHash.php';

/**
 * Controller für die Verwaltung von Admin-Benutzern
 * Nur Admin-Benutzer dürfen auf diese Funktionalität zugreifen
 */
class AdminUserInputController {
    private UserModel $model;
    public string $message = "";
    public string $messageType = "info";
    public array $modalData = [];
    public string $redirectUrl = "AdminUserInputView.php";

    public function __construct(UserModel $model) {
        $this->model = $model;
    }

    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
            enforce_post_same_origin();
            csrf_require();
        }

        // Passwort-Update
        if (isset($_POST['update_password'])) {
            $this->handlePasswordUpdate();
            return;
        }

        // SSO-E-Mail aktualisieren
        if (isset($_POST['update_admin_sso'])) {
            $this->handleSsoEmailUpdate();
            return;
        }

        // Löschen eines Admin-Nutzers
        if (isset($_POST['delete_admin_user'])) {
            $deleteId = intval($_POST['delete_id']);

            // Verhindere Selbstlöschung
            if ($deleteId == $_SESSION['id']) {
                $this->message = "Sie können sich nicht selbst löschen.";
                $this->messageType = "error";
                return;
            }

            // Benutzer-Details für Benachrichtigung holen
            $userToDelete = $this->model->read($deleteId);
            $username = $userToDelete ? $userToDelete['username'] : 'unbekannt';

            if ($this->model->delete($deleteId)) {
                $_SESSION['notification_message'] = "Admin-Account '{$username}' wurde erfolgreich gelöscht.";
                $_SESSION['notification_type'] = "success";
                header("Location: " . $this->redirectUrl);
                exit;
            } else {
                $this->message = "Fehler beim Löschen des Admin-Nutzers.";
                $this->messageType = "error";
            }
        }

        // Hinzufügen oder Aktualisieren eines Admin-Nutzers
        if (isset($_POST['add_admin_user'])) {
            $username = trim($_POST['username']);
            $password = trim($_POST['password'] ?? '');
            $passwordConfirm = trim($_POST['password_confirm'] ?? '');
            $ssoEmail = trim($_POST['sso_email'] ?? '');
            $acc_typ = "Admin"; // Fest auf Admin gesetzt

            // Validierung der Eingaben
            if (empty($username)) {
                $this->message = "Benutzername ist erforderlich.";
                $this->messageType = "error";
                return;
            }

            if (strlen($username) < 3) {
                $this->message = "Benutzername muss mindestens 3 Zeichen lang sein.";
                $this->messageType = "error";
                return;
            }

            if ($ssoEmail !== '' && !filter_var($ssoEmail, FILTER_VALIDATE_EMAIL)) {
                $this->message = "SSO-E-Mail ist ungültig.";
                $this->messageType = "error";
                return;
            }

            if ($ssoEmail !== '' && $this->model->isSsoEmailTaken($ssoEmail)) {
                $this->message = "Diese SSO-E-Mail ist bereits einem anderen Benutzer zugeordnet.";
                $this->messageType = "error";
                return;
            }

            if ($password === '' && $ssoEmail === '') {
                $this->message = "Passwort oder SSO-E-Mail ist erforderlich.";
                $this->messageType = "error";
                return;
            }

            if ($password !== '') {
                if ($password !== $passwordConfirm) {
                    $this->message = "Passwörter stimmen nicht überein.";
                    $this->messageType = "error";
                    return;
                }
            }

            $passwordHash = null;
            if ($password !== '') {
                $passwordHash = PasswordHash::hash($password);
            }

            $confirmUpdate = isset($_POST['confirm_update']) && $_POST['confirm_update'] == "1";
            $providedDuplicateId = isset($_POST['duplicate_id']) ? intval($_POST['duplicate_id']) : null;

            $entry = [
                'username' => $username,
                'passwordHash' => $passwordHash,
                'acc_typ' => $acc_typ,
                'mannschaft_ID' => null, // Admins haben keine Mannschaft
                'station_ID' => null,    // Admins haben keine Station
                'sso_email' => $ssoEmail !== '' ? $ssoEmail : null,
            ];

            $result = $this->model->addOrUpdateUser($entry, $confirmUpdate, $providedDuplicateId);

            if ($result['status'] === 'duplicate') {
                // Daten für das modale Fenster speichern
                $this->modalData = [
                    'message' => $result['message'],
                    'duplicate_id' => $result['duplicate_id'],
                    'username' => $username,
                    'passwordHash' => $passwordHash,
                    'acc_typ' => $acc_typ,
                    'sso_email' => $ssoEmail,
                ];
            } elseif ($result['status'] === 'created') {
                $_SESSION['notification_message'] = "Admin-Account '{$username}' wurde erfolgreich erstellt.";
                $_SESSION['notification_type'] = "success";
                header("Location: " . $this->redirectUrl . "?view=overview");
                exit;
            } elseif ($result['status'] === 'updated') {
                $_SESSION['notification_message'] = "Admin-Account '{$username}' wurde erfolgreich aktualisiert.";
                $_SESSION['notification_type'] = "success";
                header("Location: " . $this->redirectUrl . "?view=overview");
                exit;
            } else {
                $this->message = isset($result['message']) ? $result['message'] : "Ein unbekannter Fehler ist aufgetreten.";
                $this->messageType = "error";
            }
        }
    }

    private function handleSsoEmailUpdate(): void
    {
        $userId = intval($_POST['user_id'] ?? 0);
        $ssoEmail = trim($_POST['sso_email'] ?? '');

        $existingUser = $this->model->read($userId);
        if (!$existingUser || $existingUser['acc_typ'] !== 'Admin') {
            $this->message = "Admin-Account nicht gefunden.";
            $this->messageType = "error";
            return;
        }

        if ($ssoEmail !== '' && !filter_var($ssoEmail, FILTER_VALIDATE_EMAIL)) {
            $this->message = "SSO-E-Mail ist ungültig.";
            $this->messageType = "error";
            return;
        }

        if ($ssoEmail !== '' && $this->model->isSsoEmailTaken($ssoEmail, $userId)) {
            $this->message = "Diese SSO-E-Mail ist bereits einem anderen Benutzer zugeordnet.";
            $this->messageType = "error";
            return;
        }

        if ($this->model->updateSsoEmail($userId, $ssoEmail !== '' ? $ssoEmail : null)) {
            $_SESSION['notification_message'] = "SSO-E-Mail für '{$existingUser['username']}' wurde gespeichert.";
            $_SESSION['notification_type'] = "success";
            header("Location: " . $this->redirectUrl . "?view=overview");
            exit;
        }

        $this->message = "Fehler beim Speichern der SSO-E-Mail.";
        $this->messageType = "error";
    }

    /**
     * Behandelt die Passwort-Aktualisierung
     */
    private function handlePasswordUpdate() {
        $userId = intval($_POST['user_id']);
        $newPassword = trim($_POST['new_password']);

        // Validierung
        if (empty($newPassword)) {
            echo json_encode(['success' => false, 'message' => 'Passwort darf nicht leer sein.']);
            exit;
        }

        // Überprüfen, ob Benutzer existiert
        $existingUser = $this->model->read($userId);
        if (!$existingUser) {
            echo json_encode(['success' => false, 'message' => 'Benutzer nicht gefunden.']);
            exit;
        }

        // Überprüfen, ob es sich um einen Admin handelt
        if ($existingUser['acc_typ'] !== 'Admin') {
            echo json_encode(['success' => false, 'message' => 'Nur Admin-Accounts können hier verwaltet werden.']);
            exit;
        }

        // Neues Passwort hashen
        $newPasswordHash = PasswordHash::hash($newPassword);

        // Passwort aktualisieren
        $success = $this->model->updatePassword($userId, $newPasswordHash);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Passwort erfolgreich aktualisiert.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Aktualisieren des Passworts.']);
        }
        exit;
    }

    /**
     * Prüft, ob der aktuelle Benutzer Admin-Rechte hat
     *
     * @return bool True wenn Admin, sonst false
     */
    public static function hasAdminPermissions(): bool {
        return isset($_SESSION['acc_typ']) && $_SESSION['acc_typ'] === 'Admin';
    }
}