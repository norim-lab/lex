<?php
// Debugging aktivieren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$secret = 'MironSecureDeploy2026';
$logFile = 'deploy.log';

function logMsg($msg) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $msg\n", FILE_APPEND);
}

// Token Check
if (($_GET['token'] ?? '') !== $secret) {
    logMsg("Access denied: Invalid token");
    http_response_code(403);
    die('Access denied');
}

logMsg("Deploy gestartet...");

// Verzeichnis wechseln
chdir(__DIR__);
logMsg("Verzeichnis: " . getcwd());

// Git Pull ausführen
// Wir nutzen 'git pull origin main' explizit
$output = [];
$returnVar = 0;
exec("git pull origin main 2>&1", $output, $returnVar);

$outputStr = implode("\n", $output);
logMsg("Git Output:\n$outputStr");
logMsg("Return Code: $returnVar");

if ($returnVar === 0) {
    echo "<h1>Deploy Success!</h1><pre>$outputStr</pre>";
} else {
    echo "<h1>Deploy Failed!</h1><pre>$outputStr</pre>";
    http_response_code(500);
}
?>