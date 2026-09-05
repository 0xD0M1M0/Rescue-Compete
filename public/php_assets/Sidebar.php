<?php
// Sidebar.php - Einfache Wettkampf-Sidebar
// Unterstützt die Wettkampf-Erstellung in logischen Schritten

require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();

// Prüfen, ob Benutzer die Berechtigung hat, die Sidebar zu sehen
$canUseSidebar = isset($_SESSION['acc_typ']) && ($_SESSION['acc_typ'] === 'Wettkampfleitung' || $_SESSION['acc_typ'] === 'Admin');

if (!$canUseSidebar) {
    return; // Sidebar wird nicht angezeigt
}

// Aktuelle Seite für Markierung ermitteln
$currentPage = basename($_SERVER['PHP_SELF']);
$currentPath = $_SERVER['REQUEST_URI'];

/**
 * Prüft, ob der aktuelle Link aktiv ist
 */
function isActiveSidebarLink($targetPage, $currentPage, $currentPath) {
    $targetBasename = basename($targetPage);
    if ($targetBasename === $currentPage) {
        return true;
    }

    // Zusätzliche Prüfung für Pfade mit Parametern
    if (strpos($currentPath, $targetPage) !== false) {
        return true;
    }

    return false;
}

/**
 * Generiert CSS-Klassen für Links
 */
function getSidebarLinkClasses($targetPage, $currentPage, $currentPath) {
    $classes = ['sidebar-link'];

    if (isActiveSidebarLink($targetPage, $currentPage, $currentPath)) {
        $classes[] = 'active';
    }

    return implode(' ', $classes);
}
?>

<!-- Wettkampf-Sidebar -->
<aside class="competition-sidebar">
    <!-- Sidebar Content -->
    <div class="sidebar-content">
        <!-- Grundsetup -->
        <div class="sidebar-section">
            <h3 class="section-title">Teams & Stationen</h3>
            <ul class="sidebar-menu">
                <li>
                    <a href="../view/TeamInputView.php"
                       class="<?php echo getSidebarLinkClasses('../view/TeamInputView.php', $currentPage, $currentPath); ?>">
                        Mannschaften
                    </a>
                </li>
                <li>
                    <a href="../view/ScoringInputView.php"
                       class="<?php echo getSidebarLinkClasses('../view/ScoringInputView.php', $currentPage, $currentPath); ?>">
                        Wertung
                    </a>
                </li>
                <li>
                    <a href="../view/StationInputView.php"
                       class="<?php echo getSidebarLinkClasses('../view/StationInputView.php', $currentPage, $currentPath); ?>">
                        Stationen
                    </a>
                </li>
                <li>
                    <a href="../view/StaffelInputView.php"
                       class="<?php echo getSidebarLinkClasses('../view/StaffelInputView.php', $currentPage, $currentPath); ?>">
                        Staffeln
                    </a>
                </li>
                <li>
                    <a href="../view/ProtocolInputView.php"
                       class="<?php echo getSidebarLinkClasses('../view/ProtocolInputView.php', $currentPage, $currentPath); ?>">
                        Protokolle
                    </a>
                </li>
            </ul>
        </div>

        <!-- Inhalte -->
        <div class="sidebar-section">
            <h3 class="section-title">Quiz-System</h3>
            <ul class="sidebar-menu">
                <li>
                    <a href="../view/QuestionInputView.php"
                       class="<?php echo getSidebarLinkClasses('../view/QuestionInputView.php', $currentPage, $currentPath); ?>">
                        Fragen & Antworten
                    </a>
                </li>
                <li>
                    <a href="../view/FormCollectionView.php"
                       class="<?php echo getSidebarLinkClasses('../view/FormCollectionView.php', $currentPage, $currentPath); ?>">
                        FormularGruppen
                    </a>
                </li>
            </ul>
        </div>
    </div>
</aside>

<!-- JavaScript für Sidebar-Funktionalität -->
<script src="../js/SidebarScript.js"></script>