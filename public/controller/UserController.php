<?php
namespace Nutzer;

use Station\UserModel;

require_once __DIR__ . '/../auth/PasswordHash.php';

class UserController {
    private UserModel $model;
    public string $message = "";
    public string $messageType = "info";
    public array $modalData = [];
    public string $redirectUrl = "UserInputView.php";

    private const ALLOWED_ROLES = ['Wartend', 'Wettkampfleitung', 'Schiedsrichter', 'Teilnehmer'];

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

        // Rolle / SSO-E-Mail aktualisieren
        if (isset($_POST['update_user_meta'])) {
            $this->handleUserMetaUpdate();
            return;
        }

        // Löschen eines Nutzers
        if (isset($_POST['delete_user'])) {
            $deleteId = intval($_POST['delete_id']);

            // Prüfen, ob es sich um einen Admin-Account handelt
            $userToDelete = $this->model->read($deleteId);
            if ($userToDelete && $userToDelete['acc_typ'] === 'Admin') {
                $this->message = "Admin-Accounts können nur über die Admin-Verwaltung gelöscht werden.";
                $this->messageType = "error";
                return;
            }

            if ($this->model->delete($deleteId)) {
                $_SESSION['notification_message'] = "Benutzer wurde erfolgreich gelöscht.";
                $_SESSION['notification_type'] = "success";
                header("Location: " . $this->redirectUrl);
                exit;
            } else {
                $this->message = "Fehler beim Löschen des Nutzers.";
                $this->messageType = "error";
            }
        }

        // Hinzufügen oder Aktualisieren eines Nutzers
        if (isset($_POST['add_user'])) {
            $username     = trim($_POST['username']);
            $password     = trim($_POST['password'] ?? '');
            $passwordConfirm = trim($_POST['password_confirm'] ?? '');
            $acc_typ      = trim($_POST['acc_typ']);
            $ssoEmail     = trim($_POST['sso_email'] ?? '');
            // Nur Admins dürfen SSO-E-Mails zuweisen (verhindert E-Mail-Squatting)
            if ($ssoEmail !== '' && (!$this->isCurrentUserAdmin())) {
                $this->message = "Nur Administratoren dürfen SSO-E-Mails zuweisen.";
                $this->messageType = "error";
                return;
            }

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

            if (strlen($username) > 32) {
                $this->message = "Benutzername darf maximal 32 Zeichen lang sein.";
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

            // Lokales Passwort oder SSO-E-Mail (für Übernahme) erforderlich
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

            if (empty($acc_typ) || !in_array($acc_typ, self::ALLOWED_ROLES, true)) {
                $this->message = "Account-Typ ist erforderlich.";
                $this->messageType = "error";
                return;
            }

            // Sicherheitsprüfung: Verhindere Admin-Erstellung über normale Benutzerverwaltung
            if ($acc_typ === 'Admin') {
                $this->message = "Admin-Accounts können nur über die Admin-Verwaltung erstellt werden.";
                $this->messageType = "error";
                return;
            }

            // Spezifische Validierung für Teilnehmer
            if ($acc_typ === 'Teilnehmer' && (!isset($_POST['team_number']) || empty($_POST['team_number']))) {
                $this->message = "Teilnehmer müssen einem Team zugeordnet werden.";
                $this->messageType = "error";
                return;
            }

            $passwordHash = null;
            if ($password !== '') {
                $passwordHash = PasswordHash::hash($password);
            }

            // Mannschaft_ID verarbeiten
            $mannschaft_ID = "";
            if(array_key_exists("team_number", $_POST) && !empty($_POST['team_number'])) {
                $mannschaft_ID = trim($_POST['team_number']);
            }

            $confirmUpdate = isset($_POST['confirm_update']) && $_POST['confirm_update'] == "1";
            $providedDuplicateId = isset($_POST['duplicate_id']) ? intval($_POST['duplicate_id']) : null;

            $entry = [
                'username'      => $username,
                'passwordHash'  => $passwordHash,
                'acc_typ' => $acc_typ,
                'mannschaft_ID' => $mannschaft_ID,
                'station_ID' => null,
                'sso_email' => $ssoEmail !== '' ? $ssoEmail : null,
            ];

            $result = $this->model->addOrUpdateUser($entry, $confirmUpdate, $providedDuplicateId);

            if ($result['status'] === 'duplicate') {
                // Daten für das modale Fenster speichern
                $this->modalData = [
                    'message'      => $result['message'],
                    'duplicate_id' => $result['duplicate_id'],
                    'username'     => $username,
                    'passwordHash'  => $passwordHash,
                    'acc_typ' => $acc_typ,
                    'mannschaft_ID' => $mannschaft_ID,
                    'sso_email' => $ssoEmail,
                ];
            } elseif ($result['status'] === 'created') {
                $_SESSION['notification_message'] = "Benutzer '{$username}' wurde erfolgreich erstellt.";
                $_SESSION['notification_type'] = "success";
                header("Location: " . $this->redirectUrl . "?view=overview");
                exit;
            } elseif ($result['status'] === 'updated') {
                $_SESSION['notification_message'] = "Benutzer '{$username}' wurde erfolgreich aktualisiert.";
                $_SESSION['notification_type'] = "success";
                header("Location: " . $this->redirectUrl . "?view=overview");
                exit;
            } else {
                $this->message = isset($result['message']) ? $result['message'] : "Ein unbekannter Fehler ist aufgetreten.";
                $this->messageType = "error";
            }
        }
    }

    private function handleUserMetaUpdate(): void
    {
        $userId = intval($_POST['user_id'] ?? 0);
        $accTyp = trim($_POST['acc_typ'] ?? '');
        $ssoEmail = trim($_POST['sso_email'] ?? '');

        if ($ssoEmail !== '' && (!$this->isCurrentUserAdmin())) {
            $this->message = "Nur Administratoren dürfen SSO-E-Mails zuweisen.";
            $this->messageType = "error";
            return;
        }

        $existingUser = $this->model->read($userId);
        if (!$existingUser) {
            $this->message = "Benutzer nicht gefunden.";
            $this->messageType = "error";
            return;
        }

        if ($existingUser['acc_typ'] === 'Admin') {
            $this->message = "Admin-Accounts können nur über die Admin-Verwaltung verwaltet werden.";
            $this->messageType = "error";
            return;
        }

        if (!in_array($accTyp, self::ALLOWED_ROLES, true)) {
            $this->message = "Ungültiger Account-Typ.";
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

        if ($accTyp === 'Teilnehmer' && empty($existingUser['mannschaft_ID'])) {
            $this->message = "Teilnehmer müssen einem Team zugeordnet sein. Bitte Benutzer neu anlegen oder Team setzen.";
            $this->messageType = "error";
            return;
        }

        $roleOk = $this->model->updateAccTyp($userId, $accTyp);
        $emailOk = true;
        if ($this->isCurrentUserAdmin()) {
            $emailOk = $this->model->updateSsoEmail($userId, $ssoEmail !== '' ? $ssoEmail : null);
        }

        if ($roleOk || ($this->isCurrentUserAdmin() && $emailOk)) {
            // Role update may report 0 rows if unchanged; still treat email update as success
            $_SESSION['notification_message'] = "Benutzer '{$existingUser['username']}' wurde aktualisiert.";
            $_SESSION['notification_type'] = "success";
            header("Location: " . $this->redirectUrl . "?view=overview");
            exit;
        }

        // Beide unverändert oder Fehler — wenn Werte gleich, trotzdem OK
        if ($existingUser['acc_typ'] === $accTyp
            && strtolower((string)($existingUser['sso_email'] ?? '')) === strtolower($ssoEmail)) {
            $_SESSION['notification_message'] = "Keine Änderungen.";
            $_SESSION['notification_type'] = "info";
            header("Location: " . $this->redirectUrl . "?view=overview");
            exit;
        }

        $this->message = "Fehler beim Aktualisieren des Benutzers.";
        $this->messageType = "error";
    }

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

        // Sicherheitsprüfung: Verhindere Admin-Passwort-Änderung über normale Verwaltung
        if ($existingUser['acc_typ'] === 'Admin') {
            echo json_encode(['success' => false, 'message' => 'Admin-Accounts können nur über die Admin-Verwaltung verwaltet werden.']);
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

    private function isCurrentUserAdmin(): bool
    {
        return isset($_SESSION['acc_typ']) && $_SESSION['acc_typ'] === 'Admin';
    }
}
