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
// Versuche verschiedene Methoden
$outputStr = "";
$returnVar = 0;

if (function_exists('shell_exec')) {
    logMsg("Using shell_exec...");
    $outputStr = shell_exec("git pull origin main 2>&1");
    if (empty($outputStr)) {
        $outputStr = "Command executed but no output returned.";
    }
} elseif (function_exists('exec')) {
    logMsg("Using exec...");
    exec("git pull origin main 2>&1", $output, $returnVar);
    $outputStr = implode("\n", $output);
} elseif (function_exists('system')) {
    logMsg("Using system...");
    ob_start();
    system("git pull origin main 2>&1", $returnVar);
    $outputStr = ob_get_clean();
} else {
    logMsg("No execution function available!");
    $outputStr = "Error: No execution function available (exec, shell_exec, system disabled)";
    $returnVar = 1;
}

logMsg("Git Output:\n$outputStr");


if ($returnVar === 0) {
    echo "<h1>Deploy Success!</h1><pre>$outputStr</pre>";
} else {
    echo "<h1>Deploy Failed! (Exit Code: $returnVar)</h1>";
    echo "<pre>$outputStr</pre>";
    // http_response_code(500); // Entfernt, damit wir den Fehler sehen!
}
?>