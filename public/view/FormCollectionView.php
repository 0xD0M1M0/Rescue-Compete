<?php
require_once '../db/DbConnection.php';
require_once '../model/FormCollectionModel.php';
require_once '../controller/FormCollectionController.php';
require_once '../php_assets/CustomAlertBox.php';
require_once __DIR__ . '/../php_assets/SecurityHelpers.php';

use FormCollection\FormCollectionModel;
use FormCollection\FormCollectionController;

require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();

// Weiterleitung, wenn Berechtigungen nicht Korrekt sind
$allowedAccountTypes = ['Wettkampfleitung', 'Admin'];
if(!isset($_SESSION["acc_typ"]) || !in_array($_SESSION["acc_typ"], $allowedAccountTypes)){
    header("Location: ../index.php");
    exit;
}

if (!isset($conn)) {
    die("Datenbankverbindung nicht verfügbar.");
}

// Controller instanziieren und Request verarbeiten
$controller = new FormCollectionController($conn);
$controller->handleRequest();

// Falls es ein AJAX-Request war, ist die Verarbeitung bereits abgeschlossen
// und die Funktion wird nicht mehr erreicht (wegen exit in sendJsonResponse)

// Daten für die View
$collections = $controller->collections;
$questionPools = $controller->questionPools;
$stations = $controller->stations;
$message = $controller->message;
$messageType = $controller->messageType;
$currentCollection = $controller->currentCollection;
$selectedQuestions = $controller->selectedQuestions;
$collectionTokens = $controller->collectionTokens;
$performanceStats = $controller->performanceStats;
$teamProgress = $controller->teamProgress;
$validationErrors = $controller->validationErrors;

// Aktuelle Ansicht bestimmen — Controller kann den Tab nach POST-Aktionen
// erzwingen (z. B. Edit-Tab nach update_time_limit-Fehler).
$currentView = sanitize_view_param(
    $controller->currentView ?? ($_GET['view'] ?? null),
    ['overview', 'create', 'edit', 'qrcodes', 'performance'],
    'overview'
);
$collectionId = isset($_GET['collection_id']) ? intval($_GET['collection_id']) : null;

$pageTitle = "Formular Verwaltung";
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RescueCompete - <?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logos/ww-favicon.ico">
    <link rel="stylesheet" href="../css/Colors.css">
    <link rel="stylesheet" href="../css/GlobalLayout.css">
    <link rel="stylesheet" href="../css/Navbar.css">
    <link rel="stylesheet" href="../css/Sidebar.css">
    <link rel="stylesheet" href="../css/Footer.css">
    <link rel="stylesheet" href="../css/Components.css">
    <link rel="stylesheet" href="../css/FormCollectionViewStyling.css">
    <!-- QR-Code-Bibliothek -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body class="has-navbar">
<!-- Navbar -->
<?php include '../php_assets/Navbar.php'; ?>

<div class="container">
    <!-- Sidebar -->
    <?php include '../php_assets/Sidebar.php'; ?>

    <!-- Hauptinhalt -->
    <div class="main-content vertical">

        <!-- Navigation Tabs -->
        <div class="tab-navigation">
            <button class="tab-button <?php echo $currentView === 'overview' ? 'active' : ''; ?>"
                    data-tab="overview"
                    onclick="showTab('overview')">Übersicht</button>
            <button class="tab-button <?php echo $currentView === 'create' ? 'active' : ''; ?>"
                    data-tab="create"
                    onclick="showTab('create')">Neue Formular-Gruppe</button>
            <button class="tab-button <?php echo $currentView === 'edit' ? 'active' : ''; ?>"
                    data-tab="edit"
                    onclick="showTab('edit')">Zeit bearbeiten</button>
            <button class="tab-button <?php echo $currentView === 'qrcodes' ? 'active' : ''; ?>"
                    data-tab="qrcodes"
                    onclick="showTab('qrcodes')">QR-Codes</button>
            <button class="tab-button <?php echo $currentView === 'performance' ? 'active' : ''; ?>"
                    data-tab="performance"
                    onclick="showTab('performance')">Statistiken</button>
        </div>

        <!-- Statusmeldungen -->
        <?php if (!empty($message)): ?>
            <div class="message-box <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Tab: Übersicht -->
        <div id="overview" class="tab-content <?php echo $currentView === 'overview' ? 'active' : ''; ?>">
            <div class="data-container">
                <div class="actions-bar">
                    <button class="btn primary-btn" onclick="showTab('create')">
                        Neue Formular-Gruppe erstellen
                    </button>
                    <button class="btn" onclick="processExpiredForms()">
                        Abgelaufene Formulare verarbeiten
                    </button>
                </div>

                <?php if (empty($collections)): ?>
                    <div class="no-data">
                        <p>Keine Formular-Gruppen vorhanden.</p>
                        <p><a href="#" onclick="showTab('create')">Erstellen Sie Ihre erste Formular-Gruppe</a></p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th width="20%">Name</th>
                            <th width="12%">Station</th>
                            <th width="8%">Fragen</th>
                            <th width="8%">Formulare</th>
                            <th width="12%">Abgeschlossen</th>
                            <th width="10%">Zeitlimit</th>
                            <th width="12%">Erstellt</th>
                            <th width="18%">Aktionen</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($collections as $collection): ?>
                            <tr>
                                <td class="collection-name-cell">
                                    <strong><?php echo htmlspecialchars($collection['name']); ?></strong>
                                    <?php if (!empty($collection['description'])): ?>
                                        <br><div class="collection-description"><?php echo htmlspecialchars($collection['description']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($collection['stationName'] ?? '-'); ?></td>
                                <td class="numeric-cell"><?php echo intval($collection['totalQuestions']); ?></td>
                                <td class="numeric-cell"><?php echo intval($collection['formsCount']); ?></td>
                                <td class="numeric-cell">
                                    <?php echo intval($collection['completedForms']); ?>/<?php echo intval($collection['totalInstances']); ?>
                                    <?php if ($collection['totalInstances'] > 0): ?>
                                        <br><small>(<?php echo $collection['completionRate']; ?>%)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="numeric-cell">
                                    <?php echo gmdate("i:s", $collection['timeLimit']); ?> min
                                </td>
                                <td><?php echo date('d.m.Y', strtotime($collection['createdAt'])); ?></td>
                                <td class="action-cell">
                                    <div class="button-group">
                                        <button class="btn small" onclick="viewTokens(<?php echo $collection['ID']; ?>)">
                                            QR-Codes
                                        </button>
                                        <button class="btn small" onclick="editTimeLimit(<?php echo $collection['ID']; ?>)">
                                            Zeit
                                        </button>
                                        <button class="btn warning-btn small" onclick="confirmDeleteCollection(<?php echo (int)$collection['ID']; ?>, <?php echo json_encode_for_js($collection['name']); ?>)">
                                            Löschen
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Neue Formular-Gruppe -->
        <div id="create" class="tab-content <?php echo $currentView === 'create' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>Neue Formular-Gruppe erstellen</h3>

                <form method="POST" id="createCollectionForm">
                    <input type="hidden" name="action" value="create_collection">

                    <div class="form-group">
                        <label for="name">Name der Formular-Gruppe *</label>
                        <input type="text" id="name" name="name" required
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                               placeholder="z.B. Erste-Hilfe-Quiz"
                               class="<?php echo $controller->hasValidationError('name') ? 'error' : ''; ?>">
                        <?php if ($controller->hasValidationError('name')): ?>
                            <div class="validation-message show"><?php echo htmlspecialchars($controller->getValidationError('name')); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="description">Beschreibung</label>
                        <textarea id="description" name="description" rows="3" maxlength="200"
                                  placeholder="Optionale Beschreibung der Formular-Gruppe (max. 200 Zeichen)"
                                  class="<?php echo $controller->hasValidationError('description') ? 'error' : ''; ?>"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <div class="character-counter">
                            <span id="description-counter">0</span>/200 Zeichen
                        </div>
                        <?php if ($controller->hasValidationError('description')): ?>
                            <div class="validation-message show"><?php echo htmlspecialchars($controller->getValidationError('description')); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="station_id">Station</label>
                        <select id="station_id" name="station_id">
                            <option value="">Keine Station zuordnen</option>
                            <?php foreach ($stations as $station): ?>
                                <option value="<?php echo $station['ID']; ?>"
                                    <?php echo (isset($_POST['station_id']) && $_POST['station_id'] == $station['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($station['name']); ?> (Nr. <?php echo $station['Nr']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="time_limit">Zeitlimit pro Formular (Sekunden) *</label>
                        <input type="number" id="time_limit" name="time_limit"
                               min="10" max="1800" value="<?php echo $_POST['time_limit'] ?? '180'; ?>" required
                               class="<?php echo $controller->hasValidationError('timeLimit') ? 'error' : ''; ?>">
                        <small>Empfohlen: 180 Sekunden (3 Minuten)</small>
                        <?php if ($controller->hasValidationError('timeLimit')): ?>
                            <div class="validation-message show"><?php echo htmlspecialchars($controller->getValidationError('timeLimit')); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="forms_count">Anzahl der Formulare *</label>
                        <input type="number" id="forms_count" name="forms_count"
                               min="1" max="20" value="<?php echo $_POST['forms_count'] ?? '4'; ?>" required
                               class="<?php echo $controller->hasValidationError('formsCount') ? 'error' : ''; ?>">
                        <small>Anzahl verschiedener Formulare, die aus den Fragen erstellt werden</small>
                        <?php if ($controller->hasValidationError('formsCount')): ?>
                            <div class="validation-message show"><?php echo htmlspecialchars($controller->getValidationError('formsCount')); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="question_pool">Fragenpool auswählen *</label>
                        <select id="question_pool" name="question_pool" required
                                class="<?php echo $controller->hasValidationError('totalQuestions') ? 'error' : ''; ?>">
                            <option value="">Bitte wählen...</option>
                            <?php foreach ($questionPools as $pool): ?>
                                <option value="<?php echo $pool['ID']; ?>"
                                    <?php echo (isset($_POST['question_pool']) && $_POST['question_pool'] == $pool['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pool['Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($controller->hasValidationError('totalQuestions')): ?>
                            <div class="validation-message show"><?php echo htmlspecialchars($controller->getValidationError('totalQuestions')); ?></div>
                        <?php endif; ?>
                    </div>

                    <div id="questionsContainer" style="display: none;">
                        <div class="form-group">
                            <label>Fragen auswählen *</label>
                            <div class="question-selection">
                                <button type="button" class="btn secondary-btn" onclick="selectAllQuestions()">
                                    Alle auswählen
                                </button>
                                <button type="button" class="btn secondary-btn" onclick="deselectAllQuestions()">
                                    Alle abwählen
                                </button>
                                <span id="selection-counter" class="selection-counter">
                                    <strong>0</strong> Fragen ausgewählt
                                </span>
                            </div>
                            <div id="questionsList" class="questions-list">
                                <!-- Dynamisch geladen via JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- Container für Fragen aus zuvor gewählten Pools (pool-übergreifende Auswahl) -->
                    <div id="hiddenSelectedQuestions" style="display: none;"></div>

                    <div class="form-actions">
                        <button type="submit" class="btn primary-btn">Formular-Gruppe erstellen</button>
                        <button type="button" class="btn" onclick="showTab('overview')">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab: Zeit bearbeiten -->
        <div id="edit" class="tab-content <?php echo $currentView === 'edit' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>Zeitlimit einer Formular-Gruppe ändern</h3>

                <div class="message-box info" style="margin-bottom: 1rem;">
                    <strong>Hinweis:</strong> Die Änderung gilt nur für Formulare, die ab jetzt
                    <em>neu gestartet</em> werden. Bereits laufende oder abgeschlossene Formulare
                    behalten ihr ursprüngliches Zeitlimit.
                </div>

                <?php if (empty($collections)): ?>
                    <div class="no-data">
                        <p>Keine Formular-Gruppen vorhanden.</p>
                        <p><a href="#" onclick="showTab('create')">Erstellen Sie zuerst eine Formular-Gruppe</a></p>
                    </div>
                <?php else: ?>
                    <form method="POST" id="updateTimeLimitForm">
                        <input type="hidden" name="action" value="update_time_limit">

                        <div class="form-group">
                            <label for="edit_collection_id">Formular-Gruppe *</label>
                            <select id="edit_collection_id" name="collection_id" required onchange="onEditCollectionChange()">
                                <option value="">Bitte wählen...</option>
                                <?php foreach ($collections as $collection): ?>
                                    <option value="<?php echo $collection['ID']; ?>"
                                            data-current-time="<?php echo intval($collection['timeLimit']); ?>"
                                        <?php echo (isset($_POST['collection_id']) && $_POST['collection_id'] == $collection['ID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($collection['name']); ?>
                                        (aktuell: <?php echo gmdate("i:s", $collection['timeLimit']); ?> min)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Aktuelles Zeitlimit</label>
                            <input type="text" id="edit_current_time_display" readonly
                                   value="—"
                                   style="background-color: var(--ww-grey-50, #f5f5f5);">
                        </div>

                        <div class="form-group">
                            <label for="edit_time_limit">Neues Zeitlimit (Sekunden) *</label>
                            <input type="number" id="edit_time_limit" name="time_limit"
                                   min="10" max="1800" value="<?php echo htmlspecialchars($_POST['time_limit'] ?? ''); ?>" required
                                   class="<?php echo $controller->hasValidationError('timeLimit') ? 'error' : ''; ?>">
                            <small>Erlaubt: 10 – 1800 Sekunden (max. 30 Minuten)</small>
                            <?php if ($controller->hasValidationError('timeLimit')): ?>
                                <div class="validation-message show"><?php echo htmlspecialchars($controller->getValidationError('timeLimit')); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn primary-btn">Zeitlimit speichern</button>
                            <button type="button" class="btn" onclick="showTab('overview')">Abbrechen</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: QR-Codes -->
        <div id="qrcodes" class="tab-content <?php echo $currentView === 'qrcodes' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>QR-Codes verwalten</h3>

                <div class="collection-selector">
                    <label for="qr_collection_select">Formular-Gruppe auswählen:</label>
                    <select id="qr_collection_select" onchange="loadCollectionTokens()">
                        <option value="">Bitte wählen...</option>
                        <?php foreach ($collections as $collection): ?>
                            <option value="<?php echo $collection['ID']; ?>"
                                <?php echo ($collectionId == $collection['ID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($collection['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="tokensContainer">
                    <?php if (!empty($collectionTokens)): ?>
                        <div class="qr-codes-grid">
                            <?php foreach ($collectionTokens as $index => $token): ?>
                                <div class="qr-code-item">
                                    <h4><?php echo htmlspecialchars($token['collectionName']); ?> - Formular <?php echo $token['formNumber']; ?></h4>
                                    <div class="qr-code-container" id="qrcode-<?php echo $index; ?>"
                                         data-url="<?php echo htmlspecialchars($token['qrCodeUrl']); ?>"></div>
                                    <div class="qr-code-info">
                                        <p><strong>Token:</strong> <?php echo htmlspecialchars($token['token']); ?></p>
                                        <p><strong>URL:</strong> <a href="<?php echo htmlspecialchars($token['qrCodeUrl']); ?>" target="_blank">
                                                <?php echo htmlspecialchars($token['qrCodeUrl']); ?>
                                            </a></p>
                                        <button class="btn download-btn" onclick="downloadQrCode(<?php echo (int)$index; ?>, <?php echo json_encode_for_js($token['collectionName'] . '_Form_' . $token['formNumber']); ?>)">
                                            QR-Code herunterladen
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <p>Keine QR-Codes verfügbar. Wählen Sie eine Formular-Gruppe aus.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab: Statistiken -->
        <div id="performance" class="tab-content <?php echo $currentView === 'performance' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>Statistiken</h3>

                <?php if (empty($performanceStats) && empty($teamProgress)): ?>
                    <div class="no-data">
                        <p>Keine Statistiken verfügbar. Erstellen Sie zuerst Formular-Gruppen und lassen Sie Teams Formulare ausfüllen.</p>
                    </div>
                <?php else: ?>

                    <?php
                    // Team-Fortschritt nach Formular-Gruppen gruppieren
                    $groupedByCollection = [];
                    foreach ($teamProgress as $progress) {
                        $collId = $progress['collectionId'];
                        if (!isset($groupedByCollection[$collId])) {
                            $groupedByCollection[$collId] = [
                                'name' => $progress['collectionName'],
                                'teams' => []
                            ];
                        }
                        $groupedByCollection[$collId]['teams'][] = $progress;
                    }

                    // Performance-Daten als Lookup
                    $perfLookup = [];
                    foreach ($performanceStats as $stat) {
                        $perfLookup[$stat['collectionId']] = $stat;
                    }
                    ?>

                    <?php foreach ($groupedByCollection as $collId => $group): ?>
                        <?php $perf = $perfLookup[$collId] ?? null; ?>
                        <div class="collection-stats-block">
                            <h4><?php echo htmlspecialchars($group['name']); ?></h4>

                            <?php if ($perf): ?>
                                <?php
                                $completionPct = $perf['totalInstances'] > 0
                                    ? round(($perf['completedInstances'] / $perf['totalInstances']) * 100)
                                    : 0;
                                ?>
                                <div class="stats-cards-row">
                                    <div class="stat-card">
                                        <div class="stat-value"><?php echo $perf['formsCount']; ?></div>
                                        <div class="stat-label">Formulare</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value"><?php echo $perf['totalQuestions']; ?></div>
                                        <div class="stat-label">Fragen</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value"><?php echo $perf['teamsAssigned']; ?></div>
                                        <div class="stat-label">Teams</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value"><?php echo $perf['completedInstances']; ?><span class="stat-value-sub">/<?php echo $perf['totalInstances']; ?></span></div>
                                        <div class="stat-label">Abgeschlossen</div>
                                        <div class="stat-bar">
                                            <div class="stat-bar-fill" style="width:<?php echo $completionPct; ?>%;background:<?php echo $completionPct == 100 ? '#28a745' : 'var(--ww-blue-100)'; ?>;"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <table class="data-table">
                                <thead>
                                <tr>
                                    <th width="30%">Mannschaft</th>
                                    <th width="25%">Kreisverband</th>
                                    <th width="15%">Abgeschlossen</th>
                                    <th width="10%">Punkte</th>
                                    <th width="20%">Fortschritt</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                // Teams nach Punkten absteigend sortieren
                                $teams = $group['teams'];
                                usort($teams, function($a, $b) {
                                    return $b['totalPoints'] - $a['totalPoints'];
                                });
                                ?>
                                <?php foreach ($teams as $team): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($team['Teamname']); ?></td>
                                        <td><?php echo htmlspecialchars($team['Kreisverband']); ?></td>
                                        <td class="numeric-cell"><?php echo $team['completedForms']; ?>/<?php echo $team['totalForms']; ?></td>
                                        <td class="numeric-cell"><strong><?php echo $team['totalPoints']; ?></strong></td>
                                        <td class="numeric-cell">
                                            <div class="progress-bar-container" style="display:inline-block;width:80px;height:12px;background:#e0e0e0;border-radius:6px;vertical-align:middle;">
                                                <div style="width:<?php echo $team['completionPercentage']; ?>%;height:100%;background:<?php echo $team['completionPercentage'] == 100 ? '#28a745' : '#007bff'; ?>;border-radius:6px;"></div>
                                            </div>
                                            <?php echo $team['completionPercentage']; ?>%
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<?php
// Formular-Gruppen-Löschung bestätigen
echo CustomAlertBox::renderSimpleConfirm(
    "confirmDeleteCollectionModal",
    "Formular-Gruppe löschen",
    "Möchten Sie diese Formular-Gruppe wirklich löschen? Alle zugehörigen Formulare, Antworten und QR-Codes werden ebenfalls gelöscht.",
    "deleteCollection()",
    "closeModal('confirmDeleteCollectionModal')"
);

// Erfolgs-/Fehlermeldungen
if (!empty($message)):
    $alertType = ($messageType === 'success') ? 'Erfolg' :
        (($messageType === 'error') ? 'Fehler' : 'Hinweis');
    echo CustomAlertBox::renderSimpleAlert(
        "messageAlert",
        $alertType,
        $message
    );
    echo '<script>document.getElementById("messageAlert").classList.add("active");</script>';
endif;
?>

<!-- JavaScript einbinden -->
<script src="../js/ModalUtils.js"></script>
<script src="../js/FormCollectionViewScript.js"></script>

<!-- Tab-Initialisierung sicherstellen -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sicherstellen, dass der korrekte Tab angezeigt wird
        const currentView = <?php echo json_encode_for_js($currentView); ?>;
        showTab(currentView);

        // Character Counter initialisieren - mit Verzögerung für Tab-Switching
        setTimeout(function() {
            initializeCharacterCounter();
        }, 100);

        // Character Counter auch bei Tab-Wechsel neu initialisieren
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                setTimeout(function() {
                    initializeCharacterCounter();
                }, 100);
            });
        });
    });
</script>

<?php include '../php_assets/Footer.php'; ?>

</body>
</html>