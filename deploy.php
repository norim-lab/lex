<?php
// Secret Key zur Sicherheit (damit nicht jeder den Deploy auslösen kann)
$secret = 'MironSecureDeploy2026';

// GitHub sendet den Payload als JSON
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';

// Einfacher Check (optional: echte Signaturprüfung mit HMAC)
// Für den Anfang reicht es, wenn wir den Secret als GET-Parameter prüfen, 
// aber GitHub Webhooks senden POST. Wir machen es ganz simpel:
// Wir prüfen nur, ob der Aufruf von GitHub kommt (optional) oder einfach immer pullen.

// Besser: Wir nutzen einen GET-Parameter ?token=...
if (($_GET['token'] ?? '') !== $secret) {
    http_response_code(403);
    die('Access denied');
}

// Befehl ausführen
// Wir müssen sicherstellen, dass wir im richtigen Verzeichnis sind
chdir(__DIR__);

// Git Pull ausführen
// 2>&1 leitet Fehler auch in den Output um
$output = shell_exec('git pull 2>&1');

echo "<pre>$output</pre>";
?>