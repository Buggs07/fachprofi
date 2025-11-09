<?php
header('Content-Type: application/json');
session_start();

require_once 'db.php';
$db = Database::getConnection();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Benutzername und Passwort erforderlich']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT id, password_hash, coins, xp, level FROM users WHERE username = ?");
        $stmt->bindValue(1, $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $username,
                    'coins' => $user['coins'],
                    'xp' => $user['xp'],
                    'level' => $user['level']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Ungültige Anmeldedaten']);
        }
        break;
        
    case 'register':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Alle Felder erforderlich']);
            exit;
        }
        
        if (strlen($username) < 3) {
            http_response_code(400);
            echo json_encode(['error' => 'Benutzername mindestens 3 Zeichen']);
            exit;
        }
        
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->bindValue(1, $username, SQLITE3_TEXT);
        $stmt->bindValue(2, $password_hash, SQLITE3_TEXT);
        
        if ($stmt->execute()) {
            $user_id = $db->lastInsertRowID();
            $_SESSION['user_id'] = $user_id;
            
            // Unlock first category
            $stmt = $db->prepare("INSERT INTO user_unlocked_categories (user_id, category_id) VALUES (?, 1)");
            $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user_id,
                    'username' => $username,
                    'coins' => 100,
                    'xp' => 0,
                    'level' => 1
                ]
            ]);
        } else {
            http_response_code(409);
            echo json_encode(['error' => 'Benutzername existiert bereits']);
        }
        break;
        
    case 'logout':
        session_destroy();
        echo json_encode(['success' => true]);
        break;
        
    case 'check':
        if (isset($_SESSION['user_id'])) {
            $stmt = $db->prepare("SELECT id, username, coins, xp, level FROM users WHERE id = ?");
            $stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $user = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($user) {
                echo json_encode(['logged_in' => true, 'user' => $user]);
            } else {
                echo json_encode(['logged_in' => false]);
            }
        } else {
            echo json_encode(['logged_in' => false]);
        }
        break;
}
?>