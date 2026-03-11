<?php
// MySQL/MariaDB Zugangsdaten
$host = 'localhost';
$dbname = 'miron777_lex';
$username = 'miron777_lex';
$password = 'LexwareSecure2026!';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Tabelle für Kontoauszüge (Statements) erstellen
    $pdo->exec("CREATE TABLE IF NOT EXISTS statements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255),
        period VARCHAR(50), -- z.B. '2025-07'
        opening_balance DECIMAL(10, 2),
        closing_balance DECIMAL(10, 2),
        status ENUM('pending', 'verified') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabelle für Transaktionen aktualisieren/erstellen
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        statement_id INT,
        date DATE NOT NULL,
        purpose TEXT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        type VARCHAR(20) NOT NULL,
        category VARCHAR(50),
        currency VARCHAR(10) DEFAULT 'EUR',
        source VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_transaction (date, purpose(255), amount),
        FOREIGN KEY (statement_id) REFERENCES statements(id) ON DELETE CASCADE
    )");

} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Datenbankfehler: ' . $e->getMessage()]));
}

// API-Endpunkte
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'statements') {
        // Liste aller Kontoauszüge
        $stmt = $pdo->query("SELECT * FROM statements ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    } elseif ($action === 'transactions') {
        // Transaktionen für einen bestimmten Auszug oder alle
        $statementId = $_GET['statement_id'] ?? null;
        if ($statementId) {
            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE statement_id = ? ORDER BY date ASC");
            $stmt->execute([$statementId]);
        } else {
            $stmt = $pdo->query("SELECT * FROM transactions ORDER BY date DESC");
        }
        
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($transactions as &$t) $t['amount'] = (float)$t['amount'];
        echo json_encode($transactions);
    } else {
        // Default: Alle Transaktionen (für Kompatibilität)
        $stmt = $pdo->query("SELECT * FROM transactions ORDER BY date DESC");
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($transactions as &$t) $t['amount'] = (float)$t['amount'];
        echo json_encode($transactions);
    }

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'create_statement') {
        // Neuen Kontoauszug + Transaktionen anlegen
        try {
            $pdo->beginTransaction();
            
            // 1. Statement einfügen
            $stmt = $pdo->prepare("INSERT INTO statements (filename, period, opening_balance, closing_balance, status) VALUES (:filename, :period, :opening, :closing, 'pending')");
            $stmt->execute([
                ':filename' => $input['filename'],
                ':period' => $input['period'] ?? null,
                ':opening' => $input['openingBalance'],
                ':closing' => $input['closingBalance']
            ]);
            $statementId = $pdo->lastInsertId();
            
            // 2. Transaktionen einfügen
            $stmtTrans = $pdo->prepare("INSERT IGNORE INTO transactions (statement_id, date, purpose, amount, type, category, currency, source) VALUES (:sid, :date, :purpose, :amount, :type, :category, :currency, :source)");
            
            $savedCount = 0;
            foreach ($input['transactions'] as $t) {
                $stmtTrans->execute([
                    ':sid' => $statementId,
                    ':date' => $t['date'],
                    ':purpose' => $t['purpose'],
                    ':amount' => $t['amount'],
                    ':type' => $t['type'],
                    ':category' => $t['category'] ?? 'sonstiges',
                    ':currency' => $t['currency'] ?? 'EUR',
                    ':source' => 'pdf-import'
                ]);
                if ($stmtTrans->rowCount() > 0) $savedCount++;
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'statement_id' => $statementId, 'saved_transactions' => $savedCount]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }

    } elseif ($action === 'verify_statement') {
        // Status auf 'verified' setzen
        $id = $input['id'];
        $stmt = $pdo->prepare("UPDATE statements SET status = 'verified' WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'add_transaction') {
        // Einzelne Transaktion hinzufügen (manuell)
        $stmt = $pdo->prepare("INSERT INTO transactions (statement_id, date, purpose, amount, type, category, source) VALUES (:sid, :date, :purpose, :amount, :type, :category, 'manual')");
        $stmt->execute([
            ':sid' => $input['statement_id'],
            ':date' => $input['date'],
            ':purpose' => $input['purpose'],
            ':amount' => $input['amount'],
            ':type' => $input['type'],
            ':category' => $input['category']
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

    } elseif ($action === 'delete_transaction') {
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
        $stmt->execute([$input['id']]);
        echo json_encode(['success' => true]);
        
    } else {
        // Fallback: Einfaches Speichern (Legacy)
        // ... (Code für Legacy POST, falls nötig, oder wir zwingen zur Nutzung von Statements)
    }

} elseif ($method === 'DELETE') {
    if ($action === 'statement') {
        $id = $_GET['id'];
        $pdo->prepare("DELETE FROM statements WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    } else {
        $pdo->exec("DELETE FROM transactions");
        $pdo->exec("DELETE FROM statements");
        echo json_encode(['success' => true]);
    }
}
?>