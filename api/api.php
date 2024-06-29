<?php

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: token, Content-Type');
    header('Access-Control-Max-Age: 1728000');
    header('Content-Length: 0');
    header('Content-Type: text/plain');
    die();
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

try {
    // Database connection
    $dsn = "sqlite:" . __DIR__ . "/database.sqlite";
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT,
        name TEXT,
        info TEXT,
        geometry TEXT,
        tileLayer TEXT
    )");

    // Get the HTTP method and data
    $method = $_SERVER['REQUEST_METHOD'];
    $data = json_decode(file_get_contents("php://input"), true);

    // Router
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getItem($pdo, $_GET['id']);
            } else {
                getItems($pdo);
            }
            break;
        case 'POST':
            if (isset($data['action'])) {
                switch ($data['action']) {
                    case 'UPDATE':
                        if (isset($data['id'])) {
                            updateItem($pdo, $data['id'], $data);
                        } else {
                            http_response_code(400);
                            echo json_encode(["error" => "ID is required for update"]);
                        }
                        break;
                    case 'DELETE':
                        if (isset($data['id'])) {
                            deleteItem($pdo, $data['id']);
                        } else {
                            http_response_code(400);
                            echo json_encode(["error" => "ID is required for delete"]);
                        }
                        break;
                    case 'INSERT':
                        createItem($pdo, $data);
                        break;
                    default:
                        break;
                }
            } else {
                createItem($pdo, $data);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(["error" => "Method not allowed"]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}

// CRUD Functions
function getItems($pdo) {
    $stmt = $pdo->query("SELECT * FROM items");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['geometry'] = json_decode($item['geometry']);
    }
    echo json_encode($items);
}

function getItem($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($item) {
        $item['geometry'] = json_decode($item['geometry']);
        echo json_encode($item);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Item not found"]);
    }
}

function createItem($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO items (type, name, info, geometry, tileLayer) VALUES (:type, :name, :info, :geometry, :tileLayer)");
    $stmt->execute([
        ':type' => $data['type'],
        ':name' => $data['name'],
        ':info' => $data['info'],
        ':geometry' => json_encode($data['geometry']),
        ':tileLayer' => $data['tileLayer']
    ]);
    $id = $pdo->lastInsertId();
    http_response_code(201);
    echo json_encode(["id" => $id, "message" => "Item created successfully"]);
}

function updateItem($pdo, $id, $data) {
    $stmt = $pdo->prepare("UPDATE items SET type = :type, name = :name, info = :info, geometry = :geometry, tileLayer = :tileLayer WHERE id = :id");
    $result = $stmt->execute([
        ':id' => $id,
        ':type' => $data['type'],
        ':name' => $data['name'],
        ':info' => $data['info'],
        ':geometry' => json_encode($data['geometry']),
        ':tileLayer' => $data['tileLayer']
    ]);
    if ($result) {
        echo json_encode(["message" => "Item updated successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update item"]);
    }
}

function deleteItem($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM items WHERE id = :id");
    $result = $stmt->execute([':id' => $id]);
    if ($result) {
        echo json_encode(["message" => "Item deleted successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to delete item"]);
    }
}