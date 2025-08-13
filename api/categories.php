<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Auth.php';

class Categories {
    private $conn;
    private $db;
    private $auth;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        $this->auth = new Auth();
    }

    private function getAuthHeaderToken(): ?string {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        if (!$authHeader) return null;
        if (stripos($authHeader, 'Bearer ') === 0) {
            return trim(substr($authHeader, 7));
        }
        return trim($authHeader);
    }

    public function authenticate() {
        $token = $this->getAuthHeaderToken();
        if (!$token) return false;
        return $this->auth->verifyToken($token);
    }

    public function getCategories($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY category_name");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    public function addCategory($data) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            if (empty($data['category_name'])) {
                return ['success' => false, 'message' => 'Category name is required'];
            }

            $stmt = $this->conn->prepare("INSERT INTO categories (user_id, category_name, color, icon) VALUES (?, ?, ?, ?)");
            
            $stmt->execute([
                $user_id,
                $data['category_name'],
                $data['color'] ?? '#3B82F6',
                $data['icon'] ?? 'tag'
            ]);

            $category_id = $this->conn->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Category added successfully',
                'category_id' => $category_id
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to add category: ' . $e->getMessage()];
        }
    }

    public function updateCategory($data) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            if (empty($data['id'])) {
                return ['success' => false, 'message' => 'Category ID is required'];
            }

            $stmt = $this->conn->prepare("SELECT user_id FROM categories WHERE id = ?");
            $stmt->execute([$data['id']]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category || (int)$category['user_id'] !== (int)$user_id) {
                return ['success' => false, 'message' => 'Category not found or unauthorized'];
            }

            $stmt = $this->conn->prepare("UPDATE categories SET category_name = ?, color = ?, icon = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([
                $data['category_name'],
                $data['color'] ?? '#3B82F6',
                $data['icon'] ?? 'tag',
                $data['id'],
                $user_id
            ]);

            return [
                'success' => true,
                'message' => 'Category updated successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to update category: ' . $e->getMessage()];
        }
    }

    public function deleteCategory($id) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            $stmt = $this->conn->prepare("SELECT user_id FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category || (int)$category['user_id'] !== (int)$user_id) {
                return ['success' => false, 'message' => 'Category not found or unauthorized'];
            }

            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM transactions WHERE category_id = ?");
            $stmt->execute([$id]);
            $count = (int)$stmt->fetchColumn();

            if ($count > 0) {
                return ['success' => false, 'message' => 'Cannot delete category that has transactions'];
            }

            $stmt = $this->conn->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            return [
                'success' => true,
                'message' => 'Category deleted successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete category: ' . $e->getMessage()];
        }
    }
}

$categories = new Categories();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user_id = $categories->authenticate();
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    echo json_encode($categories->getCategories($user_id));
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'add':
                echo json_encode($categories->addCategory($input));
                break;
            case 'update':
                echo json_encode($categories->updateCategory($input));
                break;
            case 'delete':
                echo json_encode($categories->deleteCategory($input['id'] ?? null));
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Action required']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
