# RescueCompete

**Digitale Wettkampfsoftware für Wasserwacht-Wettbewerbe**

RescueCompete ist eine speziell entwickelte Web-Anwendung zur digitalen Verwaltung und Auswertung von Wasserwacht-Wettkämpfen. Die Software wurde im Rahmen eines Designprojekts an der Technischen Hochschule Lübeck entwickelt und bereits erfolgreich bei Landes- und Bundeswettbewerben eingesetzt.

## Funktionsumfang

- **Digitale Ergebniseingabe** für Schwimm- und Parcours-Disziplinen
- **Automatische Berechnung** und Auswertung der Wettkampfergebnisse
- **Mannschafts- und Stationsverwaltung** mit flexibler Konfiguration
- **Quiz-System** für Wartepunkte mit Timer-Funktionalität
- **Benutzer- und Rechteverwaltung** mit rollenbasiertem Zugang
- **Responsive Design** für alle Endgeräte
- **QR-Code Integration** für sichere Formular-Links

## Technische Details

### Systemanforderungen
- Webserver mit PHP-Unterstützung
- MariaDB/MySQL Datenbank
- Docker-Umgebung (empfohlen)

### Architektur
- **Backend**: PHP mit MVC-Architektur
- **Frontend**: HTML, CSS, JavaScript
- **Datenbank**: MariaDB mit umfangreichen Views und Stored Procedures
- **Session-Management**: PHP Sessions mit benutzerdefinierten Rollen

### Datenbankstruktur
Die Anwendung nutzt eine komplexe Datenbankstruktur mit folgenden Hauptentitäten:
- Mannschaften und Wertungsklassen
- Stationen und Protokolle
- Staffeln für Schwimmwettbewerbe
- Formular-Kollektionen mit dynamischen Quizfragen
- Benutzer mit rollenbasierter Rechteverwaltung

## Installation

Voraussetzung: [Docker Desktop](https://www.docker.com/products/docker-desktop/) (oder eine andere Docker-Umgebung) muss laufen. Port 80 und 8080 sollten frei sein.

### 1. Repository klonen

```bash
git clone [repository-url]
cd Rescue-Compete
```

### 2. Umgebungsvariablen anlegen

```bash
cp .env.example .env
```

`.env` anpassen (mindestens die Datenbankpasswörter). Beispielinhalt:

```env
MYSQL_ROOT_PASSWORD=change-me-root
MYSQL_DATABASE=webappdb
MYSQL_USER=rescue
MYSQL_PASSWORD=change-me-user
OIDC_ENABLED=0
```

OIDC-Variablen siehe Abschnitt [Single Sign-On](#drkserver-single-sign-on-optional) bzw. Kommentare in `.env.example`.

Beim ersten Start lädt MariaDB das Schema aus `sql-scheme/webappdb-V6-1.sql` in die angegebene Datenbank.

### 3. Container starten

```bash
docker compose up -d
```

Beim ersten Mal (oder nach Änderungen am `Dockerfile`) einmal mit Build:

```bash
docker compose up -d --build
```

`docker-compose.override.yml` wird automatisch mitgeladen und veröffentlicht:

| Dienst     | URL                       |
|------------|---------------------------|
| Anwendung  | http://localhost          |
| phpMyAdmin | http://localhost:8080     |

Stoppen: `docker compose down`  
Datenbankvolumen mit löschen: `docker compose down -v`

### 4. Ersten Admin-Benutzer anlegen

Die Datenbank enthält zunächst keine Benutzer. Über phpMyAdmin (http://localhost:8080) die Datenbank (Name siehe .env - z.B.MYSQL_DATABASE: **webappdb**) auswählen und ausführen:

```sql
USE webappdb;

INSERT INTO `User` (username, passwordHash, acc_typ, mannschaft_ID, station_ID)
VALUES (
  'admin',
  '5ede13f8c4f4b1416e9c7837629fd1bf',
  'Admin',
  NULL,
  NULL
);
```

Danach unter http://localhost/view/Login.php anmelden:

- Benutzername: `admin`
- Passwort: `admin`

Das Standardpasswort danach ändern. Weitere Accounts legt man nach dem Login unter **Benutzer** bzw. **Admin-Verwaltung** an.

### Produktion

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

Damit starten die App hinter Caddy (Ports 80/443) statt mit dem lokalen Override.

### 5. Bestehende Datenbank: SSO-Migration

Bei einer bereits initialisierten Datenbank (Docker-Volume) einmalig ausführen:

```bash
docker compose exec -T db mariadb -u root -p"$MYSQL_ROOT_PASSWORD" < sql-scheme/migrations/2026-09-05-sso.sql
```

Oder in phpMyAdmin den Inhalt von `sql-scheme/migrations/2026-09-05-sso.sql` ausführen (Datenbank **webappdb**).

Neue Installationen erhalten die SSO-Spalten bereits über `sql-scheme/webappdb-V6-1.sql`.

## drkserver Single Sign-On (optional)

RescueCompete kann parallel zum lokalen Login [drkserver SSO](https://www.drkserver.org/drkserver/funktionen/single-sign-on.html) (OpenID Connect) nutzen. SSO prüft nur die Identität; Rollen bleiben in RescueCompete.

### Client beim drkserver-Team beantragen

E-Mail an **support@drkserver.org** mit Betreff `SSO-Client einrichten` und:

- Name des Systems: RescueCompete
- Kurzbeschreibung der Nutzung
- Redirect-URL, z. B. `https://your-host/controller/OidcSsoCallback.php`
- Ansprechpartner (Name, E-Mail, DRK-Gliederung)

Antwort enthält typischerweise Issuer-URL, Client-ID und Client-Secret.

### Umgebungsvariablen

In `.env` ergänzen und Container neu starten (`docker compose up -d`):

```env
OIDC_ENABLED=1
OIDC_ISSUER=https://example-issuer.drkserver.org
OIDC_CLIENT_ID=your-client-id
OIDC_CLIENT_SECRET=your-client-secret
OIDC_REDIRECT_URI=https://your-host/controller/OidcSsoCallback.php
OIDC_LOGIN_LABEL=Mit drkserver anmelden
```

`OIDC_LOGIN_LABEL` steuert den Text des SSO-Buttons (Standard: `Mit SSO anmelden`).

Ohne diese Variablen (oder `OIDC_ENABLED=0`) bleibt nur die lokale Anmeldung sichtbar.

### Verhalten

| Szenario | Ergebnis |
|----------|----------|
| Admin ohne drkserver | Lokales Passwort-Login wie bisher |
| Admin mit SSO-E-Mail | Erster passender SSO-Login verknüpft den Admin-Account |
| Admin legt Nutzer mit Rolle + SSO-E-Mail an | Erster passender drkserver-Login verknüpft den Account und behält die Rolle |
| Unbekannter drkserver-Login | Neuer Nutzer mit Rolle **Wartend**; Admin weist später eine Rolle zu |
| Lokaler Nutzer ohne SSO-Felder | Unverändert per Benutzername/Passwort |

Für echte SSO-Tests braucht die Redirect-URI eine öffentlich erreichbare HTTPS-URL (oder einen Tunnel). Lokal reicht weiterhin das Passwort-Login.

### Lokal testen mit Keycloak (optional)

Als Ersatz für drkserver gibt es ein Compose-Overlay mit Keycloak:

```bash
docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.keycloak.yml up -d
```

Das Overlay setzt die OIDC-Env-Variablen am App-Container (unabhängig von `.env`) und startet Keycloak:

| Was | Wert |
|-----|------|
| Keycloak Admin | http://localhost:8081 — `admin` / `admin` |
| Realm | `rescuecompete` |
| Client | `rescue-compete` / Secret `local-dev-secret` |
| Testnutzer | `sso-user` / `sso-user` (E-Mail `sso-user@example.com`) |
| Redirect URI | `http://localhost/controller/OidcSsoCallback.php` |
| Button-Text | `Mit Keycloak anmelden` (via `OIDC_LOGIN_LABEL`) |

Realm-Import: [`docker/keycloak/realm-rescuecompete.json`](docker/keycloak/realm-rescuecompete.json).

Danach unter http://localhost/view/Login.php den SSO-Button nutzen (IdP ist lokal Keycloak).

## Benutzerrollen

### Administrator
- Vollzugriff auf alle Funktionen
- Wettkampf- und Website-Verwaltung
- Benutzerverwaltung

### Wettkampfleitung
- Ergebniseingabe und -verwaltung
- Mannschafts- und Stationsverwaltung
- Auswertungen und Berichte

### Schiedsrichter
- Ergebniseingabe für zugewiesene Stationen
- Zugriff auf relevante Formulare

### Teilnehmer
- Zugang zu Quiz-Formularen
- Einsicht in Wettkampfinformationen

### Wartend
- Angemeldet (z. B. nach erstem SSO), aber ohne Menürechte
- Wartet auf Rollenzuweisung durch einen Administrator

## Entwicklungsteam

**Jonas Richter** - Projektmanager und Entwickler  
Stellvertretender technischer Leiter der Wasserwacht Thüringen

**Sven Meiburg** - Entwickler

**Prof. Dr. Monique Janneck** - Projektbetreuung  
Technische Hochschule Lübeck

## Praxiseinsätze

Die Software wurde bereits erfolgreich eingesetzt bei:
- **Sachsen/Thüringen-Meisterschaften 2025** - Vollständig digitaler Wettkampf
- **Bundesmeisterschaften 2025** - Hybride Lösung mit MS Forms Integration

## Open Source & Verfügbarkeit

RescueCompete wird als **Open-Source-Projekt** veröffentlicht. Jede Wasserwacht-Gliederung kann die Software kostenlos nutzen, selbst hosten und weiterentwickeln.

### Hosted Service
Für Organisationen ohne eigene technische Infrastruktur wird ein gehosteter Service unter **rescue-compete.de** angeboten.

## Support

**Fehler melden oder technische Unterstützung:**  
E-Mail: jonas-richter@email.de

**Projektanfragen und Implementierung:**  
Hilfe bei der Einrichtung von Wettkämpfen und Schulungen sind auf Anfrage kostenlos verfügbar.

## Lizenz

Dieses Projekt wird ohne Gewährleistung bereitgestellt. Die Nutzung erfolgt auf eigene Verantwortung.
Mehr ist in den Nutzungsrichtlinien nachzulesen.

## Haftungsausschluss

Der Support erfolgt im Rahmen verfügbarer Ressourcen. Bei kritischen Fehlern während Wettkämpfen stehen wir nach Möglichkeit zur Verfügung, können aber keine 24/7-Betreuung garantieren.

---

*Entwickelt mit Unterstützung der Technischen Hochschule Lübeck im Rahmen eines Designprojekts für die Wasserwacht des Deutschen Roten Kreuzes.*