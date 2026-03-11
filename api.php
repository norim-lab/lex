<?php
// SQLite Datenbank-Verbindung
$dbFile = 'database.sqlite';
$dsn = "sqlite:$dbFile";

try {
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Tabelle erstellen, falls nicht vorhanden
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date TEXT NOT NULL,
        purpose TEXT NOT NULL,
        amount REAL NOT NULL,
        type TEXT NOT NULL,
        category TEXT,
        currency TEXT DEFAULT 'EUR',
        source TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(date, purpose, amount)
    )");

} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Datenbankfehler: ' . $e->getMessage()]));
}

// API-Endpunkte
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Alle Transaktionen abrufen
    $stmt = $pdo->query("SELECT * FROM transactions ORDER BY date DESC, created_at DESC");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($transactions);

} elseif ($method === 'POST') {
    // Neue Transaktionen speichern
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !is_array($input)) {
        http_response_code(400);
        die(json_encode(['error' => 'Ungültige Daten']));
    }

    $savedCount = 0;
    $errors = [];

    $stmt = $pdo->prepare("INSERT OR IGNORE INTO transactions (date, purpose, amount, type, category, currency, source) VALUES (:date, :purpose, :amount, :type, :category, :currency, :source)");

    foreach ($input as $t) {
        try {
            $stmt->execute([
                ':date' => $t['date'], // Format: YYYY-MM-DD
                ':purpose' => $t['purpose'],
                ':amount' => $t['amount'],
                ':type' => $t['type'], // credit/debit
                ':category' => $t['category'] ?? 'sonstiges',
                ':currency' => $t['currency'] ?? 'EUR',
                ':source' => $t['source'] ?? 'unknown'
            ]);
            if ($stmt->rowCount() > 0) {
                $savedCount++;
            }
        } catch (PDOException $e) {
            $errors[] = $e->getMessage();
        }
    }

    echo json_encode([
        'success' => true,
        'saved' => $savedCount,
        'ignored' => count($input) - $savedCount,
        'errors' => $errors
    ]);

} elseif ($method === 'DELETE') {
    // Alles löschen (Vorsicht!)
    // Optional: Passwortschutz hier einbauen
    $pdo->exec("DELETE FROM transactions");
    echo json_encode(['success' => true, 'message' => 'Alle Daten gelöscht']);
}
?>