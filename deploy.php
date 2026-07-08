<?php
/**
 * DEAKTIVIERT - diese Datei ist absichtlich ein Stub.
 *
 * Frueher konnte hierueber ein produktiver "git pull" ausgeloest werden
 * (shell_exec / exec / system). Das war ein ernsthafter Angriffsvektor:
 * Jeder, der den Token kannte (er stand im Repo), konnte auf dem Server
 * beliebige Git-Operationen - und damit effektiv Code - ausfuehren.
 *
 * Deployments laufen jetzt ausschliesslich ueber GitHub Actions
 * (.github/workflows/deploy.yml) per SCP mit SSH-Key, der NICHT im
 * Repo liegt. Diese Datei existiert nur noch, damit ein eventuell noch
 * auf dem Server vorhandener alter Stand beim naechsten Deploy
 * ueberschrieben wird und keinen Schaden mehr anrichten kann.
 *
 * Es werden bewusst KEINE Shell-Funktionen mehr aufgerufen.
 */
http_response_code(410); // Gone
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'error' => 'Deploy ueber diese Datei ist deaktiviert. Deployments laufen via GitHub Actions.',
]);
