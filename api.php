<?php
// Legendary score board API (Marvel, Alien, James Bond, Predator, Matrix, Firefly)
// Secrets live in config.php, which is NOT in git. See config.example.php.

header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'config.php is missing on the server']);
    exit;
}
$config = require $configFile;

const FRANCHISES = ['marvel', 'alien', 'bond', 'predator', 'matrix', 'firefly'];

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

function initTables(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        franchise VARCHAR(32) NOT NULL DEFAULT 'marvel',
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
        franchise VARCHAR(32) NOT NULL DEFAULT 'marvel',
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

    // Migration from the Marvel-only version: existing rows become 'marvel'.
    foreach (['cards', 'games'] as $table) {
        $has = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'franchise'")->fetch();
        if (!$has) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN franchise VARCHAR(32) NOT NULL DEFAULT 'marvel' AFTER id");
        }
    }
}
initTables($pdo);

function readBody() {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function requirePassword(array $data, string $expected) {
    $provided = (string)($data['password'] ?? '');
    if ($expected === '' || !hash_equals($expected, $provided)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid password']);
        exit;
    }
}

function franchiseOf(array $data) {
    $f = $data['franchise'] ?? '';
    if (!in_array($f, FRANCHISES, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown game']);
        exit;
    }
    return $f;
}

function intOrNull($v) {
    return ($v === null || $v === '') ? null : (int)$v;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'load':
        $cards = $pdo->query("SELECT id, franchise, expansion, type, name, comment, ranking FROM cards ORDER BY franchise, expansion, type, name")->fetchAll(PDO::FETCH_ASSOC);
        $cards = array_map(function ($c) {
            $c['id'] = (int)$c['id'];
            $c['ranking'] = intOrNull($c['ranking']);
            return $c;
        }, $cards);

        $games = $pdo->query("SELECT * FROM games ORDER BY franchise, expansion, name")->fetchAll(PDO::FETCH_ASSOC);
        $games = array_map(function ($g) {
            return [
                'id' => (int)$g['id'],
                'franchise' => $g['franchise'],
                'expansion' => $g['expansion'],
                'name' => $g['name'],
                'comment' => $g['comment'],
                'ranking' => intOrNull($g['ranking']),
                'victoryPile' => (int)$g['victory_pile'],
                'bystanders' => (int)$g['bystanders'],
                'bystanderPenalty' => (int)$g['bystander_penalty'],
                'twists' => (int)$g['twists'],
                'twistPenalty' => (int)$g['twist_penalty'],
                'escaped' => (int)$g['villains_escaped'],
                'escapePenalty' => (int)$g['escape_penalty'],
                'sum' => (int)$g['total_score'],
                'heroes' => $g['heroes'],
                'result' => $g['result'],
            ];
        }, $games);

        echo json_encode(['cards' => $cards, 'games' => $games]);
        break;

    case 'checkPassword':
        requirePassword(readBody(), $config['edit_password']);
        echo json_encode(['success' => true]);
        break;

    case 'saveCard':
        $data = readBody();
        requirePassword($data, $config['edit_password']);
        $values = [
            franchiseOf($data), $data['expansion'] ?? '', $data['type'] ?? '',
            $data['name'] ?? '', $data['comment'] ?? '', intOrNull($data['ranking'] ?? null),
        ];
        if (!empty($data['id'])) {
            $stmt = $pdo->prepare("UPDATE cards SET franchise=?, expansion=?, type=?, name=?, comment=?, ranking=? WHERE id=?");
            $stmt->execute(array_merge($values, [(int)$data['id']]));
            echo json_encode(['success' => true, 'id' => (int)$data['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cards (franchise, expansion, type, name, comment, ranking) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute($values);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }
        break;

    case 'saveGame':
        $data = readBody();
        requirePassword($data, $config['edit_password']);
        $values = [
            franchiseOf($data), $data['expansion'] ?? '', $data['name'] ?? '', $data['comment'] ?? '',
            intOrNull($data['ranking'] ?? null),
            (int)($data['victoryPile'] ?? 0), (int)($data['bystanders'] ?? 0), (int)($data['bystanderPenalty'] ?? 0),
            (int)($data['twists'] ?? 0), (int)($data['twistPenalty'] ?? 0),
            (int)($data['escaped'] ?? 0), (int)($data['escapePenalty'] ?? 0),
            (int)($data['sum'] ?? 0), $data['heroes'] ?? '', $data['result'] ?? '',
        ];
        if (!empty($data['id'])) {
            $stmt = $pdo->prepare("UPDATE games SET franchise=?, expansion=?, name=?, comment=?, ranking=?, victory_pile=?, bystanders=?, bystander_penalty=?, twists=?, twist_penalty=?, villains_escaped=?, escape_penalty=?, total_score=?, heroes=?, result=? WHERE id=?");
            $stmt->execute(array_merge($values, [(int)$data['id']]));
            echo json_encode(['success' => true, 'id' => (int)$data['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO games (franchise, expansion, name, comment, ranking, victory_pile, bystanders, bystander_penalty, twists, twist_penalty, villains_escaped, escape_penalty, total_score, heroes, result) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute($values);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }
        break;

    case 'deleteCard':
    case 'deleteGame':
        $data = readBody();
        requirePassword($data, $config['edit_password']);
        $table = $action === 'deleteCard' ? 'cards' : 'games';
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ?");
        $stmt->execute([(int)($data['id'] ?? 0)]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
