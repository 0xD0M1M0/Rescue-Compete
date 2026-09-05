<?php
require_once __DIR__ . '/CookieMonster.php';
startSecureSession();

// Debug-Ausgabe (später auskommentieren oder entfernen)
// error_log("Session Status in index.php: " . print_r($_SESSION, true));

// Absolute URLs für die Weiterleitung
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = htmlspecialchars($_SERVER["HTTP_HOST"]);
$baseUrl = $protocol . $host . dirname(htmlspecialchars($_SERVER["PHP_SELF"]));

// Prüfung ob Benutzer eingeloggt ist (aber keine Weiterleitung mehr)
$isLoggedIn = isset($_SESSION["login"]) && $_SESSION["login"] === "ok";

$pageTitle = "RescueCompete";

// Benutzerinformationen für die Anzeige (falls eingeloggt)
$userName = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : null;
$userType = isset($_SESSION['acc_typ']) ? htmlspecialchars($_SESSION['acc_typ']) : null;
$isPendingRole = $isLoggedIn && isset($_SESSION['acc_typ']) && $_SESSION['acc_typ'] === 'Wartend';
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RescueCompete - Digitale Wettkampfsoftware</title>
    <meta name="description" content="RescueCompete - Professionelle Software für die Organisation und Auswertung von Wasserwacht-Wettkämpfen">
    <meta name="keywords" content="Wasserwacht, Wettkampf, Rettungsschwimmen, Software, Digital, Auswertung">
    <meta name="author" content="Jonas Richter">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/logos/ww-favicon.ico">
    <link rel="apple-touch-icon" href="assets/images/logos/ww-rundlogo.png">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="css/Colors.css">
    <link rel="stylesheet" href="css/GlobalLayout.css">
    <link rel="stylesheet" href="css/Navbar.css">
    <link rel="stylesheet" href="css/Footer.css">
    <link rel="stylesheet" href="css/LandingpageStyling.css">

    <!-- Preload wichtiger Ressourcen -->
    <link rel="preload" href="assets/images/logos/ww-rundlogo.png" as="image">
</head>
<body class="has-navbar">
<!-- Navbar -->
<?php include 'php_assets/Navbar.php'; ?>
<div class="landing-container">
    <?php if ($isPendingRole): ?>
    <section class="pending-role-banner">
        <div class="pending-role-content">
            <h2>Warte auf Rollenzuweisung</h2>
            <p>
                Sie sind angemeldet<?php echo $userName ? ' als <strong>' . $userName . '</strong>' : ''; ?>,
                haben aber noch keine Rolle. Ein Administrator muss Ihnen unter
                <em>Benutzer</em> eine Rolle zuweisen, bevor Sie Funktionen nutzen können.
            </p>
        </div>
    </section>
    <?php endif; ?>

    <!-- Hero Section für alle Benutzer -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="logo-container">
                <img src="assets/images/logos/ww-rundlogo.png" class="hero-logo" alt="Wasserwacht Logo">
            </div>
            <h1 class="hero-title">RescueCompete</h1>
            <p class="hero-subtitle">Die digitale Lösung zur Organisation und Auswertung von Wasserwacht-Wettkämpfen</p>
        </div>
    </section>

    <!-- Project Info Section -->
    <section class="info-section">
        <div class="info-grid">
            <div class="info-card">
                <h3>Über das Projekt</h3>
                <p>
                    RescueCompete ist eine speziell entwickelte Web-Anwendung für die digitale
                    Verwaltung und Auswertung von Wasserwacht-Wettkämpfen. Das Projekt wird von
                    Jonas Richter und Teresa Schaser aktiv weiterentwickelt und wächst mit den
                    Anforderungen aus dem realen Wettkampfbetrieb.
                </p>
                <div class="project-details">
                    <h4>Team & Partner</h4>
                    <div class="team-member">
                        <strong>Jonas Richter</strong><br>
                        Entwickler & Projektleiter
                    </div>
                    <div class="team-member">
                        <strong>Teresa Schaser</strong><br>
                        Organisationspartnerin
                    </div>
                    <div class="team-member">
                        <strong>Wasserwacht Sachsen</strong><br>
                        Unterstützer & Förderer
                    </div>
                </div>
            </div>

            <div class="info-card">
                <h3>Funktionsumfang</h3>
                <ul class="feature-list">
                    <li>Digitale Ergebniseingabe für Schwimm- und Parcours-Disziplinen</li>
                    <li>Automatische Berechnung und Auswertung</li>
                    <li>Mannschafts- und Stationsverwaltung</li>
                    <li>Quiz-System für Wartepunkte</li>
                    <li>Benutzer- und Rollenverwaltung</li>
                    <li>Responsive Design für alle Endgeräte</li>
                </ul>
            </div>

            <div class="info-card">
                <h3>Vorteile</h3>
                <p>
                    Die Software wurde bereits erfolgreich bei Landes- und Bundeswettbewerben getestet
                    und hat sich in der Praxis bewährt.
                </p>
                <ul class="achievement-list">
                    <li>Deutliche Zeitersparnis und Personalreduzierung im Rechenbüro</li>
                    <li>Positive Resonanz von Organisatoren und Teilnehmenden</li>
                    <li>Stabile und zuverlässige Performance unter Wettkampfbedingungen</li>
                    <li>Erfolgreiches Management komplexer Wettkampfstrukturen</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Open Source Section -->
    <section class="opensource-section">
        <div class="opensource-content">
            <h2>Open Source & Verfügbarkeit</h2>
            <div class="opensource-grid">
                <div class="opensource-info">
                    <h3>Open Source Projekt</h3>
                    <p>
                        RescueCompete wird als <strong>Open-Source-Projekt</strong> veröffentlicht.
                        Jede Gliederung kann die Software kostenlos nutzen, selbst hosten und
                        weiterentwickeln.
                    </p>
                    <a href="https://github.com/LifeguardORG/Rescue-Compete" class="github-link">
                        GitHub Repository
                    </a>
                </div>
                <div class="opensource-info">
                    <h3>Hosted Service</h3>
                    <p>
                        Für Organisationen ohne eigene technische Infrastruktur bieten wir
                        einen gehosteten Service unter <strong>rescue-compete.de</strong> an.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="contact-grid">
            <div class="contact-card">
                <h3>Fehler melden</h3>
                <p>
                    Haben Sie einen Fehler entdeckt oder benötigen technische Unterstützung?
                </p>
                <div class="contact-methods">
                    <a href="mailto:jonas-richter@email.de?subject=RescueCompete%20Bug%20Report" class="contact-button">
                        E-Mail senden
                    </a>
                    <p class="contact-info">
                        <strong>E-Mail:</strong> jonas-richter@email.de<br>
                        <strong>Rolle:</strong> Entwickler & Projektleiter
                    </p>
                </div>
            </div>

            <div class="contact-card">
                <h3>Projektanfragen</h3>
                <p>
                    Interesse an der Nutzung für Ihren Wettkampf oder Fragen zur Implementierung?
                </p>
                <div class="contact-methods">
                    <a href="mailto:jonas-richter@email.de?subject=RescueCompete%20Projektanfrage" class="contact-button">
                        Anfrage stellen
                    </a>
                    <p class="contact-info">
                        <strong>Hilfe bei der Einrichtung eines Wettkampfes und Schulungen mit der Software
                            passieren auf Anfrage und sind kostenlos.</strong>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Disclaimer Section -->
    <section class="disclaimer-section">
        <div class="disclaimer-content">
            <h2>Wichtige Hinweise</h2>
            <div class="disclaimer-grid">
                <div class="disclaimer-card">
                    <h3>Nutzung auf Eigenverantwortung</h3>
                    <p>
                        Die Software wird ohne Gewährleistung bereitgestellt. Die Nutzung erfolgt
                        auf eigene Verantwortung. Die Software ist zur baldigen Veröffentlichung
                        fertiggestellt und getestet.
                    </p>
                </div>
                <div class="disclaimer-card">
                    <h3>Support & Wartung</h3>
                    <p>
                        Der Support erfolgt im Rahmen verfügbarer Ressourcen. Bei kritischen
                        Fehlern während Wettkämpfen stehen wir nach Möglichkeit zur Verfügung,
                        können aber keine 24/7-Betreuung garantieren.
                    </p>
                </div>
            </div>
        </div>
    </section>

</div>

<?php include 'php_assets/Footer.php'; ?>

<!-- JavaScript -->
<script src="js/LandingpageScript.js"></script>
<script src="js/NavbarScript.js"></script>
</body>
</html>