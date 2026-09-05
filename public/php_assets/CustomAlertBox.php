<?php
require_once __DIR__ . '/SecurityHelpers.php';

class CustomAlertBox {
    private $id;
    private string $title;
    private string $message;
    private array $buttons;
    private array $data;

    public function __construct($id = 'alertBox') {
        $this->id = $id;
        $this->title = '';
        $this->message = '';
        $this->buttons = [];
        $this->data = [];
    }

    public function setTitle($title) {
        $this->title = $title;
    }

    public function setMessage($message) {
        $this->message = $message;
    }

    public function addButton(string $text, string $action = '', string $class = 'btn', string $type = 'button') {
        $this->buttons[] = [
            'text' => $text,
            'action' => $action,
            'class' => $class,
            'type' => $type
        ];
    }

    public function setData(array $data) {
        $this->data = $data;
    }

    public function render(): string
    {
        $html = '<div id="'.htmlspecialchars($this->id).'" class="modal">';
        $html .= '<div class="modal-content">';
        if (!empty($this->title)) {
            $html .= '<h2>' . htmlspecialchars($this->title) . '</h2>';
        }
        $html .= '<p>' . htmlspecialchars($this->message) . '</p>';

        if (!empty($this->data)) {
            $html .= '<form method="post">';
            $html .= csrf_field();
            foreach ($this->data as $key => $value) {
                $html .= '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars((string)$value).'">';
            }
            $html = $this->generateAlertButtons($html);
            $html .= '</form>';
        } else {
            $html = $this->generateAlertButtons($html);
        }

        $html .= '</div></div>';
        return $html;
    }

    private function generateAlertButtons(string $html): string {
        foreach ($this->buttons as $button) {
            $html .= '<button type="' . htmlspecialchars($button['type']) . '" class="' . htmlspecialchars($button['class']) . '"';
            if (!empty($button['action'])) {
                $html .= ' onclick="' . htmlspecialchars($button['action']) . '"';
            }
            $html .= '>' . htmlspecialchars($button['text']) . '</button>';
        }
        return $html;
    }

    public static function renderSimpleAlert(string $id, string $title, string $message): string
    {
        $alert = new self($id);
        $alert->setTitle($title);
        $alert->setMessage($message);
        $alert->addButton("OK", "closeModal('{$id}');", "btn", "button");
        return $alert->render();
    }

    public static function renderSimpleConfirm(string $id, string $title, string $message, string $yesAction, string $noAction): string
    {
        $confirm = new self($id);
        $confirm->setTitle($title);
        $confirm->setMessage($message);
        $confirm->addButton("Ja", $yesAction, "btn primary-btn", "button");
        $confirm->addButton("Nein", $noAction, "btn", "button");
        return $confirm->render();
    }

    public static function renderSuccessAlert(string $id, string $title, string $message): string
    {
        $alert = new self($id);
        $alert->setTitle($title);
        $alert->setMessage($message);
        $alert->addButton("OK", "closeModal('{$id}');", "btn primary-btn", "button");
        return $alert->render();
    }

    public static function renderErrorAlert(string $id, string $title, string $message): string
    {
        $alert = new self($id);
        $alert->setTitle($title);
        $alert->setMessage($message);
        $alert->addButton("OK", "closeModal('{$id}');", "btn warning-btn", "button");
        return $alert->render();
    }

    public static function renderDuplicateConfirm(string $id, array $data, string $existingKreisverband, string $existingLandesverband): string
    {
        $confirm = new self($id);
        $confirm->setTitle("Mannschaft bereits vorhanden");
        $confirm->setMessage("Eine Mannschaft mit dem Namen \"{$data['teamname']}\" existiert bereits (Kreisverband: {$existingKreisverband}, Landesverband: {$existingLandesverband}). Möchten Sie diese mit den neuen Daten aktualisieren?");
        $confirm->setData([
            'teamname' => $data['teamname'],
            'kreisverband' => $data['kreisverband'],
            'landesverband' => $data['landesverband'],
            'duplicate_id' => $data['duplicate_id'],
            'confirm_update' => "1",
            'add_team' => "1"
        ]);
        $confirm->addButton("Ja, aktualisieren", "", "btn primary-btn", "submit");
        $confirm->addButton("Abbrechen", "closeModal('{$id}');", "btn", "button");
        return $confirm->render();
    }
}