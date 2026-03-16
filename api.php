<?php
// Marvel Legendary Stats API

// EDIT PASSWORD - Change this to your secret password
$edit_password = '131313';

// Database configuration for one.com
$db_host = 'globbin.se.mysql';
$db_name = 'globbin_se';
$db_user = 'globbin_se';
$db_pass = '131313';

// Enable CORS for local testing (remove in production if not needed)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Connect to database
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Initialize tables if they don't exist
function initTables($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expansion VARCHAR(255) NOT NULL,
        type VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        comment TEXT,
        ranking INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS games (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expansion VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        comment TEXT,
        ranking INT,
        victory_pile INT DEFAULT 0,
        bystanders INT DEFAULT 0,
        bystander_penalty INT DEFAULT 0,
        twists INT DEFAULT 0,
        twist_penalty INT DEFAULT 0,
        villains_escaped INT DEFAULT 0,
        escape_penalty INT DEFAULT 0,
        total_score INT DEFAULT 0,
        heroes TEXT,
        result VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Initialize tables on first run
initTables($pdo);

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Password check for write operations
function checkPassword($required_password) {
    $data = json_decode(file_get_contents('php://input'), true);
    $provided = $data['password'] ?? $_GET['password'] ?? '';
    if ($provided !== $required_password) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid password']);
        exit;
    }
    return $data;
}

switch ($action) {
    case 'load':
        // Load all cards and games
        $cards = $pdo->query("SELECT * FROM cards ORDER BY expansion, type, name")->fetchAll(PDO::FETCH_ASSOC);
        $games = $pdo->query("SELECT * FROM games ORDER BY expansion, name")->fetchAll(PDO::FETCH_ASSOC);

        // Convert snake_case to camelCase for JavaScript
        $games = array_map(function($game) {
            return [
                'id' => $game['id'],
                'expansion' => $game['expansion'],
                'name' => $game['name'],
                'comment' => $game['comment'],
                'ranking' => $game['ranking'],
                'victoryPile' => (int)$game['victory_pile'],
                'bystanders' => (int)$game['bystanders'],
                'bystanderPenalty' => (int)$game['bystander_penalty'],
                'twists' => (int)$game['twists'],
                'twistPenalty' => (int)$game['twist_penalty'],
                'escaped' => (int)$game['villains_escaped'],
                'escapePenalty' => (int)$game['escape_penalty'],
                'sum' => (int)$game['total_score'],
                'heroes' => $game['heroes'],
                'result' => $game['result']
            ];
        }, $games);

        echo json_encode(['cards' => $cards, 'games' => $games]);
        break;

    case 'saveCard':
        $data = checkPassword($edit_password);

        if (isset($data['id']) && $data['id']) {
            // Update existing card
            $stmt = $pdo->prepare("UPDATE cards SET expansion=?, type=?, name=?, comment=?, ranking=? WHERE id=?");
            $stmt->execute([$data['expansion'], $data['type'], $data['name'], $data['comment'] ?? '', $data['ranking'], $data['id']]);
            echo json_encode(['success' => true, 'id' => $data['id']]);
        } else {
            // Insert new card
            $stmt = $pdo->prepare("INSERT INTO cards (expansion, type, name, comment, ranking) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data['expansion'], $data['type'], $data['name'], $data['comment'] ?? '', $data['ranking']]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        }
        break;

    case 'saveGame':
        $data = checkPassword($edit_password);

        if (isset($data['id']) && $data['id']) {
            // Update existing game
            $stmt = $pdo->prepare("UPDATE games SET expansion=?, name=?, comment=?, ranking=?, victory_pile=?, bystanders=?, bystander_penalty=?, twists=?, twist_penalty=?, villains_escaped=?, escape_penalty=?, total_score=?, heroes=?, result=? WHERE id=?");
            $stmt->execute([
                $data['expansion'], $data['name'], $data['comment'] ?? '', $data['ranking'],
                $data['victoryPile'], $data['bystanders'], $data['bystanderPenalty'],
                $data['twists'], $data['twistPenalty'], $data['escaped'], $data['escapePenalty'],
                $data['sum'], $data['heroes'] ?? '', $data['result'] ?? '', $data['id']
            ]);
            echo json_encode(['success' => true, 'id' => $data['id']]);
        } else {
            // Insert new game
            $stmt = $pdo->prepare("INSERT INTO games (expansion, name, comment, ranking, victory_pile, bystanders, bystander_penalty, twists, twist_penalty, villains_escaped, escape_penalty, total_score, heroes, result) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['expansion'], $data['name'], $data['comment'] ?? '', $data['ranking'],
                $data['victoryPile'], $data['bystanders'], $data['bystanderPenalty'],
                $data['twists'], $data['twistPenalty'], $data['escaped'], $data['escapePenalty'],
                $data['sum'], $data['heroes'] ?? '', $data['result'] ?? ''
            ]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        }
        break;

    case 'deleteCard':
        $data = checkPassword($edit_password);
        $stmt = $pdo->prepare("DELETE FROM cards WHERE id = ?");
        $stmt->execute([$data['id']]);
        echo json_encode(['success' => true]);
        break;

    case 'deleteGame':
        $data = checkPassword($edit_password);
        $stmt = $pdo->prepare("DELETE FROM games WHERE id = ?");
        $stmt->execute([$data['id']]);
        echo json_encode(['success' => true]);
        break;

    case 'importInitialData':
        // Import initial dataset (run once)
        $data = checkPassword($edit_password);

        // Clear existing data
        $pdo->exec("TRUNCATE TABLE cards");
        $pdo->exec("TRUNCATE TABLE games");

        // Import cards
        $stmt = $pdo->prepare("INSERT INTO cards (expansion, type, name, comment, ranking) VALUES (?, ?, ?, ?, ?)");
        foreach ($data['cards'] as $card) {
            $stmt->execute([$card['expansion'], $card['type'], $card['name'], $card['comment'] ?? '', $card['ranking'] ?? null]);
        }

        // Import games
        $stmt = $pdo->prepare("INSERT INTO games (expansion, name, comment, ranking, victory_pile, bystanders, bystander_penalty, twists, twist_penalty, villains_escaped, escape_penalty, total_score, heroes, result) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['games'] as $game) {
            $stmt->execute([
                $game['expansion'], $game['name'], $game['comment'] ?? '', $game['ranking'] ?? null,
                $game['victoryPile'] ?? 0, $game['bystanders'] ?? 0, $game['bystanderPenalty'] ?? 0,
                $game['twists'] ?? 0, $game['twistPenalty'] ?? 0, $game['escaped'] ?? 0, $game['escapePenalty'] ?? 0,
                $game['sum'] ?? 0, $game['heroes'] ?? '', $game['result'] ?? ''
            ]);
        }

        echo json_encode(['success' => true, 'cardsImported' => count($data['cards']), 'gamesImported' => count($data['games'])]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
?>
