<?php
// Secret Key (Sicherheit): Ändern Sie dies in etwas Komplexes!
$secret = 'MySecretWebhookKey123';

// GitHub Payload prüfen
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';

if (!$signature) {
    http_response_code(403);
    die('Forbidden: No signature');
}

list($algo, $hash) = explode('=', $signature, 2);
$payloadHash = hash_hmac($algo, $payload, $secret);

if (!hash_equals($payloadHash, $hash)) {
    http_response_code(403);
    die('Forbidden: Invalid signature');
}

// Wenn Signatur OK -> Pull ausführen
// Wir leiten stderr nach stdout um (2>&1), um Fehler zu sehen
// "git pull" wird als der User ausgeführt, dem der Webserver gehört (oft www-data oder miron777)
$output = shell_exec('cd /home/miron777/web/lex.zeitblytz.media/public_html && git pull 2>&1');

echo "<pre>$output</pre>";
?>