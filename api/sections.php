<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $db->query("SELECT * FROM sections ORDER BY name ASC");
        $sections = $stmt->fetchAll();
        echo json_encode($sections);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Section name is required']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO sections (name, description) VALUES (?, ?)");
        $stmt->execute([$data['name'], $data['description'] ?? '']);
        echo json_encode(['id' => $db->lastInsertId(), 'message' => 'Section created successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        $error = $e->getMessage();
        if (strpos($error, 'UNIQUE constraint failed: sections.name') !== false) {
            $error = "Section '" . $data['name'] . "' already exists.";
        }
        echo json_encode(['error' => $error]);
    }
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id']) || empty($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID and name are required']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE sections SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$data['name'], $data['description'] ?? '', $data['id']]);
        echo json_encode(['message' => 'Section updated successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        $error = $e->getMessage();
        if (strpos($error, 'UNIQUE constraint failed: sections.name') !== false) {
            $error = "Section '" . $data['name'] . "' already exists.";
        }
        echo json_encode(['error' => $error]);
    }
} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID is required']);
        exit;
    }

    try {
        $stmt = $db->prepare("DELETE FROM sections WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['message' => 'Section deleted successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>