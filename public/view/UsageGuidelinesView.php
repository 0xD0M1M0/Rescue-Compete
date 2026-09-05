<?php

require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();
$pageTitle = "Nutzungsrichtlinien";
?>

    <!DOCTYPE html>
    <html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - RescueCompete</title>
    <link rel="stylesheet" href="../css/Colors.css">
    <link rel="stylesheet" href="../css/GlobalLayout.css">
    <link rel="stylesheet" href="../css/Navbar.css">
    <link rel="stylesheet" href="../css/Footer.css">
    <link rel="stylesheet" href="../css/UsageGuidelines.css">
</head>
<body class="has-navbar">
<?php include '../php_assets/Navbar.php'; ?>

<div class="page-container">
    <main class="main-content">
        <div class="guidelines-container">
            <div class="guidelines-header">
                <h1>Nutzungsrichtlinien</h1>
                <p class="guidelines-subtitle">
                    RescueCompete - Wettkampfsoftware für die Wasserwacht
                </p>
            </div>

            <div class="guidelines-content">
                <section class="guidelines-section">
                    <h2>Zweck der Software</h2>
                    <p>
                        RescueCompete wurde speziell für die Durchführung und Verwaltung von
                        Rettungsschwimm-Wettkämpfen der Wasserwacht des Deutschen Roten Kreuzes entwickelt.
                        Die Software dient ausschließlich ehrenamtlichen Zwecken und wird kostenfrei
                        zur Verfügung gestellt.
                    </p>
                </section>

                <section class="guidelines-section">
                    <h2>Nutzungsberechtigung</h2>
                    <ul>
                        <li>Die Software ist ausschließlich für Organisationen der Wasserwacht bestimmt</li>
                        <li>Die Nutzung erfolgt auf eigene Verantwortung</li>
                        <li>Eine kommerzielle Nutzung ist nicht gestattet</li>
                        <li>Die Weitergabe der Software an Dritte bedarf der Zustimmung der Entwickler</li>
                    </ul>
                </section>

                <section class="guidelines-section">
                    <h2>Datenschutz und Sicherheit</h2>
                    <p>
                        Die Software verarbeitet ausschließlich wettkampfbezogene Daten wie Mannschaftsnamen,
                        Ergebnisse und Punktzahlen. Es werden keine personenbezogenen Daten im Sinne der
                        DSGVO gespeichert oder verarbeitet.
                    </p>
                </section>

                <section class="guidelines-section">
                    <h2>Support und Wartung</h2>
                    <ul>
                        <li>Ein Anspruch auf Support oder Weiterentwicklung besteht nicht</li>
                        <li>Nichtsdestotrotz stellen wir Support im Rahmen unserer Kapazitäten zur Verfügung</li>
                        <li>Updates und Bugfixes erfolgen nach Priorität und Verfügbarkeit</li>
                    </ul>
                </section>

                <section class="guidelines-section">
                    <h2>Haftungsausschluss</h2>
                    <p>
                        Die Entwickler übernehmen keine Haftung für Schäden, die durch die Nutzung
                        der Software entstehen können. Die Software wird "wie besehen" zur Verfügung
                        gestellt, ohne Gewährleistung für Fehlerfreiheit oder ununterbrochene Verfügbarkeit.
                    </p>
                </section>

                <section class="guidelines-section">
                    <h2>Open Source</h2>
                    <p>
                        RescueCompete ist eine Open-Source-Software. Der Quellcode kann zur Einsicht
                        und Weiterentwicklung in GitHub abgerufen werden. Modifikationen und Aktualisierungen werden
                        gerne mit namentlicher Nennung in die Software aufgenommen.
                    </p>
                </section>

                <section class="guidelines-section">
                    <h2>Kontakt</h2>
                    <p>
                        Bei Fragen oder Problemen wenden Sie sich bitte an:<br>
                        Jonas Richter - Projektleitung

                    </p>
                </section>
            </div>

            <div class="guidelines-footer">
                <p class="last-updated">
                    Letzte Aktualisierung: <?php echo date('d.m.Y'); ?>
                </p>
                <a href="../index.php" class="back-button">
                    Zurück zur Startseite
                </a>
            </div>
        </div>
    </main>

</div>

<?php include '../php_assets/Footer.php'; ?>

</body>
    </html><?php
