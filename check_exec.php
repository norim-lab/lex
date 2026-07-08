<?php
/**
 * DEAKTIVIERT - diese Datei ist absichtlich ein Stub.
 *
 * Frueher listete diese Datei oeffentlich verfuegbare Shell-Funktionen
 * (exec, shell_exec, system, passthru, proc_open, popen) und gab sogar
 * die Ausgabe von "ls -la" preis. Das ist eine klassische Recon- und
 * Informationsleck-Schwachstelle, die Angreifern hilft, den Server
 * auszukundschaften.
 *
 * Es werden hier bewusst KEINE Shell-Funktionen abgefragt oder ausgefuehrt.
 * Die Datei existiert nur noch, damit ein eventuell noch auf dem Server
 * vorhandener alter Stand beim naechsten Deploy ueberschrieben wird.
 */
http_response_code(410); // Gone
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'error' => 'Shell-Diagnostik ueber diese Datei ist deaktiviert.',
]);
