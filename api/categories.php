<?php
header('Content-Type: application/json');
session_start();

require_once 'db.php';
$db = Database::getConnection();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht eingeloggt']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        // Get all categories with unlock status
        $query = "
            SELECT c.*, 
                   CASE WHEN uuc.category_id IS NOT NULL THEN 1 ELSE 0 END as unlocked,
                   COALESCE(up.reset_count, 0) as reset_count,
                   COALESCE(up.attempts, 0) as attempts
            FROM categories c
            LEFT JOIN user_unlocked_categories uuc ON c.id = uuc.category_id AND uuc.user_id = ?
            LEFT JOIN user_progress up ON c.id = up.category_id AND up.user_id = ? AND up.game_type = 'all'
            ORDER BY c.id
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $categories = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $categories[] = $row;
        }
        
        echo json_encode(['categories' => $categories]);
        break;
        
    case 'unlock':
        $category_id = $_POST['category_id'] ?? 0;
        
        // Check if already unlocked
        $stmt = $db->prepare("SELECT 1 FROM user_unlocked_categories WHERE user_id = ? AND category_id = ?");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $category_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($result->fetchArray()) {
            echo json_encode(['error' => 'Bereits freigeschaltet']);
            exit;
        }
        
        // Get cost
        $stmt = $db->prepare("SELECT unlock_cost FROM categories WHERE id = ?");
        $stmt->bindValue(1, $category_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $category = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$category) {
            http_response_code(404);
            echo json_encode(['error' => 'Kategorie nicht gefunden']);
            exit;
        }
        
        // Check user coins
        $stmt = $db->prepare("SELECT coins FROM users WHERE id = ?");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user['coins'] < $category['unlock_cost']) {
            echo json_encode(['error' => 'Nicht genug Münzen']);
            exit;
        }
        
        // Deduct coins and unlock
        $db->exec('BEGIN TRANSACTION');
        
        $stmt = $db->prepare("UPDATE users SET coins = coins - ? WHERE id = ?");
        $stmt->bindValue(1, $category['unlock_cost'], SQLITE3_INTEGER);
        $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        $stmt = $db->prepare("INSERT INTO user_unlocked_categories (user_id, category_id) VALUES (?, ?)");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $category_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        $db->exec('COMMIT');
        
        echo json_encode(['success' => true, 'cost' => $category['unlock_cost']]);
        break;
        
    case 'vocab':
        $category_id = $_GET['category_id'] ?? 0;
        
        // Check if unlocked
        $stmt = $db->prepare("SELECT 1 FROM user_unlocked_categories WHERE user_id = ? AND category_id = ?");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $category_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if (!$result->fetchArray()) {
            http_response_code(403);
            echo json_encode(['error' => 'Kategorie nicht freigeschaltet']);
            exit;
        }
        
        // Get vocabulary
        $stmt = $db->prepare("SELECT * FROM vocabulary WHERE category_id = ? ORDER BY id");
        $stmt->bindValue(1, $category_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $vocab = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $vocab[] = $row;
        }
        
        echo json_encode(['vocabulary' => $vocab]);
        break;
}
?>