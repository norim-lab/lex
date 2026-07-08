<?php
/**
 * Lex - API
 *
 * Aenderungen gegenueber der Vorversion (Sofort-Haertung):
 *  - DB-Zugangsdaten und API-Token liegen NICHT mehr im Code, sondern in
 *    config.php (ausserhalb des Repos, siehe .gitignore / config.example.php).
 *  - Jeder Request muss einen gueltigen Token im Header "X-API-Token"
 *    (oder als Query-Parameter "token") mitsenden. Ohne Auth gibt es ein
 *    sauberes JSON-401/403, kein stilles Versagen.
 *  - Die Fachlogik (Statements/Transactions/Learning) ist unveraendert.
 */

declare(strict_types=1);

// ------------------------------------------------------------------
// 1. Konfiguration laden (ausserhalb des Repos)
// ------------------------------------------------------------------
$CONFIG_PATH = __DIR__ . '/config.php';
if (!is_readable($CONFIG_PATH)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Serverfehler: Konfiguration fehlt.']);
    exit;
}
$config = require $CONFIG_PATH;

// ------------------------------------------------------------------
// 2. Helfer: saubere JSON-Antwort + Exit
// ------------------------------------------------------------------
function json_response(int $status, $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

// ------------------------------------------------------------------
// 3. Authentifizierung (Token via Header ODER Query)
// ------------------------------------------------------------------
function get_bearer_token(): ?string
{
    // Bevorzugt: dedizierter Header
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'X-API-Token') === 0) {
            return trim($value);
        }
    }
    // Fallback: Authorization: Bearer <token>
    $authHeader = $headers['Authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (stripos($authHeader, 'Bearer ') === 0) {
        return trim(substr($authHeader, 7));
    }
    // Letzter Fallback: Query-Parameter (fuer einfache GET-Tests)
    return isset($_GET['token']) ? trim($_GET['token']) : null;
}

$expectedToken = $config['api_token'] ?? '';
$providedToken = get_bearer_token();

if ($providedToken === null || $providedToken === '') {
    json_response(401, ['error' => 'Nicht authentifiziert: API-Token erforderlich.']);
}
if (!is_string($expectedToken) || $expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    json_response(403, ['error' => 'Zugriff verweigert: Ungueltiger API-Token.']);
}

// ------------------------------------------------------------------
// 4. Datenbankverbindung (nur mit authentifiziertem Request)
// ------------------------------------------------------------------
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']),
        $config['db_user'],
        $config['db_password'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Tabelle fuer Kontoauszuege (Statements) - mit raw_text
    $pdo->exec("CREATE TABLE IF NOT EXISTS statements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255),
        period VARCHAR(50),
        opening_balance DECIMAL(10, 2),
        closing_balance DECIMAL(10, 2),
        status ENUM('pending', 'verified') DEFAULT 'pending',
        raw_text MEDIUMTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabelle fuer Transaktionen - mit Status
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
        status ENUM('pending', 'confirmed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_transaction (date, purpose(255), amount),
        FOREIGN KEY (statement_id) REFERENCES statements(id) ON DELETE CASCADE
    )");

    // Tabelle fuer Gelerntes (Regeln & Korrekturen)
    $pdo->exec("CREATE TABLE IF NOT EXISTS learning_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rule_type VARCHAR(50),
        original_input TEXT,
        corrected_output TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration fuer existierende Tabellen (falls Spalten fehlen)
    try { $pdo->exec("ALTER TABLE statements ADD COLUMN raw_text MEDIUMTEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE transactions ADD COLUMN status ENUM('pending', 'confirmed') DEFAULT 'pending'"); } catch (Exception $e) {}

} catch (PDOException $e) {
    json_response(500, ['error' => 'Datenbankfehler.']);
}

// ------------------------------------------------------------------
// 5. API-Endpunkte (Fachlogik unveraendert)
// ------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'statements') {
        $stmt = $pdo->query("SELECT id, filename, period, opening_balance, closing_balance, status, created_at,
                             (SELECT COUNT(*) FROM transactions WHERE statement_id = statements.id AND status = 'pending') as pending_count
                             FROM statements ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } elseif ($action === 'transactions') {
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

    } elseif ($action === 'learning_rules') {
        $stmt = $pdo->query("SELECT * FROM learning_rules ORDER BY created_at DESC LIMIT 100");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } elseif ($action === 'get_raw_text') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("SELECT raw_text FROM statements WHERE id = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['raw_text' => $res['raw_text'] ?? '']);
    }

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'create_statement') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO statements (filename, period, opening_balance, closing_balance, status, raw_text) VALUES (:filename, :period, :opening, :closing, 'pending', :raw)");
            $stmt->execute([
                ':filename' => $input['filename'],
                ':period' => $input['period'] ?? null,
                ':opening' => $input['openingBalance'],
                ':closing' => $input['closingBalance'],
                ':raw' => $input['rawText'] ?? ''
            ]);
            $statementId = $pdo->lastInsertId();

            $stmtTrans = $pdo->prepare("INSERT IGNORE INTO transactions (statement_id, date, purpose, amount, type, category, currency, source, status) VALUES (:sid, :date, :purpose, :amount, :type, :category, :currency, :source, 'pending')");

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

    } elseif ($action === 'confirm_transaction') {
        $id = $input['id'];
        $stmt = $pdo->prepare("UPDATE transactions SET status = 'confirmed' WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'update_transaction') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE transactions SET date = :date, purpose = :purpose, amount = :amount, category = :category, status = 'confirmed' WHERE id = :id");
            $stmt->execute([
                ':date' => $input['date'],
                ':purpose' => $input['purpose'],
                ':amount' => $input['amount'],
                ':category' => $input['category'],
                ':id' => $input['id']
            ]);

            if (!empty($input['original_purpose']) && $input['original_purpose'] !== $input['purpose']) {
                $stmtLearn = $pdo->prepare("INSERT INTO learning_rules (rule_type, original_input, corrected_output) VALUES ('rename', :orig, :corr)");
                $stmtLearn->execute([
                    ':orig' => $input['original_purpose'],
                    ':corr' => $input['purpose']
                ]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
        }

    } elseif ($action === 'verify_statement') {
        $id = $input['id'];

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE statement_id = ? AND status = 'pending'");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Noch nicht alle Buchungen bestätigt!']);
        } else {
            $stmt = $pdo->prepare("UPDATE statements SET status = 'verified' WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        }

    } elseif ($action === 'add_transaction') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO transactions (statement_id, date, purpose, amount, type, category, source, status) VALUES (:sid, :date, :purpose, :amount, :type, :category, 'manual', 'confirmed')");
            $stmt->execute([
                ':sid' => $input['statement_id'],
                ':date' => $input['date'],
                ':purpose' => $input['purpose'],
                ':amount' => $input['amount'],
                ':type' => $input['type'],
                ':category' => $input['category']
            ]);

            if (!empty($input['context_text'])) {
                $stmtLearn = $pdo->prepare("INSERT INTO learning_rules (rule_type, original_input, corrected_output) VALUES ('missing_pattern', :ctx, :json)");
                $stmtLearn->execute([
                    ':ctx' => $input['context_text'],
                    ':json' => json_encode(['date' => $input['date'], 'amount' => $input['amount']])
                ]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
        }

    } elseif ($action === 'delete_transaction') {
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
        $stmt->execute([$input['id']]);
        echo json_encode(['success' => true]);
    }
} elseif ($method === 'DELETE') {
    if ($action === 'statement') {
        $id = $_GET['id'];
        $pdo->prepare("DELETE FROM statements WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    }
}
?>
