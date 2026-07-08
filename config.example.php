<?php
/**
 * Beispiel-Konfiguration.
 *
 * Kopiere diese Datei nach config.php (im selben Verzeichnis wie api.php)
 * und trage dort deine echten Werte ein. config.php wird NICHT ins Repo
 * committet (siehe .gitignore) und ist die einzige Stelle, an der Secrets
 * stehen duerfen.
 *
 * WICHTIG: Alle bisher im Code hartcodierten Secrets (DB-Passwort,
 * Deploy-Token etc.) sind als kompromittiert zu betrachten und MUESSEN
 * hier bzw. in der Datenbank neu vergeben werden!
 */

return [
    // Datenbank
    'db_host'     => 'localhost',
    'db_name'     => 'miron777_lex',
    'db_user'     => 'miron777_lex',
    'db_password' => 'HIER_NEUES_STARKES_PASSWORT_EINTRAGEN',

    /**
     * API-Token fuer den Zugriff auf api.php.
     * Erzeuge einen langen, zufaelligen String, z. B. mit:
     *   php -r "echo bin2hex(random_bytes(32));"
     * Trage denselben Wert im Frontend unter Einstellungen ein.
     * Der Wert wird vom Client im Header "X-API-Token" gesendet.
     */
    'api_token'   => 'HIER_LANGEN_ZUFAELLIGEN_TOKEN_EINTRAGEN',
];
