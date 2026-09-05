<?php global $pageTitle;
require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();

// Prüfung ob Benutzer eingeloggt ist
$isLoggedIn = isset($_SESSION["login"]) && $_SESSION["login"] === "ok";
$userType = isset($_SESSION['acc_typ']) ? $_SESSION['acc_typ'] : null;
?>

<nav class="navbar">
    <div class="navbar-left">
        <a href="../index.php" class="navLogo">
            <img src="../assets/images/logos/ww-rundlogo.png" alt="Logo">
        </a>
        <div class="page-title">
            <h1><?php echo $pageTitle; ?></h1>
        </div>
    </div>

    <!-- Hamburger Menü für mobile Ansicht -->
    <div class="hamburger">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <ul class="nav-menu">
        <?php if ($isLoggedIn): ?>
            <!-- EINGABE (Schiedsrichter + Wettkampfleitung) -->
            <?php if($userType === 'Schiedsrichter' || $userType === 'Wettkampfleitung' || $userType === 'Admin'): ?>
                <li class="dropdown">
                    <a href="#" class="nav-link">
                        <img src="../assets/images/icons/input.svg" alt="Eingabe" class="nav-icon">
                        Eingabe
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="../view/StaffelList.php">Schwimm-Ergebnisse</a></li>
                        <li><a href="../view/StationList.php">Parcours-Ergebnisse</a></li>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- ERGEBNISSE (nur Wettkampfleitung) -->
            <?php if($userType === 'Schiedsrichter' || $userType === 'Wettkampfleitung' || $userType === 'Admin'): ?>
                <li class="dropdown">
                    <a href="#" class="nav-link">
                        <img src="../assets/images/icons/results.svg" alt="Ergebnisse" class="nav-icon">
                        Ergebnisse
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="../view/SwimmingResultsView.php">Schwimmen</a></li>
                        <li><a href="../view/ParcoursResultsView.php">Parcours</a></li>
                        <li><a href="../view/CompleteResultsView.php">Gesamtergebnis</a></li>
                    </ul>
                </li>

                <!-- TEAMS & STATIONEN (nur Wettkampfleitung) -->
                <li class="dropdown">
                    <a href="#" class="nav-link">
                        <img src="../assets/images/icons/teams-stations.svg" alt="Teams & Stationen" class="nav-icon">
                        Teams & Stationen
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="../view/TeamInputView.php">Mannschaften</a></li>
                        <li><a href="../view/ScoringInputView.php">Wertung</a></li>
                        <li><a href="../view/StationInputView.php">Stationen</a></li>
                        <li><a href="../view/StaffelInputView.php">Staffeln</a></li>
                        <li><a href="../view/ProtocolInputView.php">Protokolle</a></li>
                    </ul>
                </li>

                <!-- QUIZ-SYSTEM (nur Wettkampfleitung) -->
                <li class="dropdown">
                    <a href="#" class="nav-link">
                        <img src="../assets/images/icons/quiz-system.svg" alt="Quiz-System" class="nav-icon">
                        Quiz-System
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="../view/QuestionInputView.php">Fragen & Antworten</a></li>
                        <li><a href="../view/FormCollectionView.php">Formular Verwaltung</a></li>
                    </ul>
                </li>

                <!-- SYSTEM (nur Wettkampfleitung) -->
                <li class="dropdown">
                    <a href="#" class="nav-link">
                        <img src="../assets/images/icons/system.svg" alt="System" class="nav-icon">
                        System
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="../view/UserInputView.php">Benutzer</a></li>
                        <li><a href="../view/ResultConfiguration.php">Punkte-Gewichtung</a></li>
                        <li><a href="../view/StationWeightInputView.php">Stationsgewichtung</a></li>
                        <li><a href="../view/QrCodes.php">QR-Codes</a></li>
                        <li><a href="../view/CompetitionResetView.php">Wettkampf Reset</a></li>
                    </ul>
                </li>
            <?php endif; ?>

            <div class="nav-divider"></div>

            <!-- LOGOUT für eingeloggte Benutzer -->
            <li>
                <a href="../Logout.php" class="nav-link logout-btn">
                    <img src="../assets/images/icons/logout.svg" alt="Logout" class="nav-icon">
                    Logout
                </a>
            </li>
        <?php else: ?>
            <!-- LOGIN für nicht angemeldete Benutzer -->
            <li>
                <a href="../view/Login.php" class="nav-link login-btn">
                    <img src="../assets/images/icons/login.svg" alt="Login" class="nav-icon">
                    Login
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>

<!-- JavaScript für die Navbar-Funktionen -->
<script src="../js/NavbarScript.js"></script>