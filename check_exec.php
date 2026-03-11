<?php
// Prüfe verfügbare Funktionen
$functions = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'];
$available = [];

foreach ($functions as $func) {
    if (function_exists($func) && !in_array($func, explode(',', ini_get('disable_functions')))) {
        $available[] = $func;
    }
}

echo "<h1>Verfügbare Shell-Funktionen:</h1>";
if (empty($available)) {
    echo "Keine! Alle sind deaktiviert.";
} else {
    echo "<ul>";
    foreach ($available as $func) {
        echo "<li>$func</li>";
    }
    echo "</ul>";
    
    // Teste die erste verfügbare Funktion mit 'ls'
    echo "<h2>Test Output (ls -la):</h2><pre>";
    $cmd = "ls -la 2>&1";
    
    if (in_array('shell_exec', $available)) {
        echo shell_exec($cmd);
    } elseif (in_array('passthru', $available)) {
        passthru($cmd);
    } elseif (in_array('system', $available)) {
        system($cmd);
    } elseif (in_array('exec', $available)) {
        exec($cmd, $out);
        echo implode("\n", $out);
    }
    echo "</pre>";
}
?>