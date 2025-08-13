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

class Budgets {
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

    public function getBudgets($user_id) {
        try {
            $sql = "SELECT b.*, c.category_name, c.color,
                    (SELECT COALESCE(SUM(amount), 0) FROM transactions 
                     WHERE user_id = b.user_id AND category_id = b.category_id 
                     AND date BETWEEN b.start_date AND b.end_date AND type = 'expense') as spent
                    FROM budgets b
                    LEFT JOIN categories c ON b.category_id = c.id
                    WHERE b.user_id = ?
                    ORDER BY b.start_date DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$user_id]);
            $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($budgets as &$budget) {
                $budget['spent'] = (float)$budget['spent'];
                $budget['amount'] = (float)$budget['amount'];
                $budget['progress'] = $budget['amount'] > 0 ? ($budget['spent'] / $budget['amount']) * 100 : 0;
                $budget['remaining'] = $budget['amount'] - $budget['spent'];
                $budget['status'] = $budget['progress'] >= 100 ? 'exceeded' : ($budget['progress'] >= 80 ? 'warning' : 'good');
            }

            return $budgets;
        } catch (Exception $e) {
            return false;
        }
    }

    public function addBudget($data) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            if (empty($data['amount']) || empty($data['start_date']) || empty($data['end_date'])) {
                return ['success' => false, 'message' => 'Amount, start date, and end date are required'];
            }

            $stmt = $this->conn->prepare("INSERT INTO budgets (user_id, category_id, amount, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $user_id,
                $data['category_id'] !== '' ? $data['category_id'] : null,
                $data['amount'],
                $data['start_date'],
                $data['end_date']
            ]);

            $budget_id = $this->conn->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Budget added successfully',
                'budget_id' => $budget_id
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to add budget: ' . $e->getMessage()];
        }
    }

    public function updateBudget($data) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            if (empty($data['id'])) {
                return ['success' => false, 'message' => 'Budget ID is required'];
            }

            $stmt = $this->conn->prepare("SELECT user_id FROM budgets WHERE id = ?");
            $stmt->execute([$data['id']]);
            $budget = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$budget || (int)$budget['user_id'] !== (int)$user_id) {
                return ['success' => false, 'message' => 'Budget not found or unauthorized'];
            }

            $stmt = $this->conn->prepare("UPDATE budgets SET category_id = ?, amount = ?, start_date = ?, end_date = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([
                $data['category_id'] !== '' ? $data['category_id'] : null,
                $data['amount'],
                $data['start_date'],
                $data['end_date'],
                $data['id'],
                $user_id
            ]);

            return [
                'success' => true,
                'message' => 'Budget updated successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to update budget: ' . $e->getMessage()];
        }
    }

    public function deleteBudget($id) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            $stmt = $this->conn->prepare("SELECT user_id FROM budgets WHERE id = ?");
            $stmt->execute([$id]);
            $budget = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$budget || (int)$budget['user_id'] !== (int)$user_id) {
                return ['success' => false, 'message' => 'Budget not found or unauthorized'];
            }

            $stmt = $this->conn->prepare("DELETE FROM budgets WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            return [
                'success' => true,
                'message' => 'Budget deleted successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete budget: ' . $e->getMessage()];
        }
    }

    public function getBudgetOverview($user_id) {
        try {
            $sql = "SELECT 
                        COALESCE(SUM(b.amount), 0) as total_budget,
                        (SELECT COALESCE(SUM(amount), 0) FROM transactions 
                         WHERE user_id = ? AND type = 'expense' 
                         AND date BETWEEN CURDATE() - INTERVAL 1 MONTH AND CURDATE()) as total_spent
                    FROM budgets b
                    WHERE b.user_id = ? 
                    AND CURDATE() BETWEEN b.start_date AND b.end_date";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$user_id, $user_id]);
            $overview = $stmt->fetch(PDO::FETCH_ASSOC);

            $overview['total_budget'] = (float)$overview['total_budget'];
            $overview['total_spent'] = (float)$overview['total_spent'];
            $overview['remaining'] = $overview['total_budget'] - $overview['total_spent'];
            $overview['progress'] = $overview['total_budget'] > 0 ? ($overview['total_spent'] / $overview['total_budget']) * 100 : 0;

            return $overview;
        } catch (Exception $e) {
            return false;
        }
    }
}

$budgets = new Budgets();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user_id = $budgets->authenticate();
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    if (isset($_GET['overview'])) {
        echo json_encode($budgets->getBudgetOverview($user_id));
    } else {
        echo json_encode($budgets->getBudgets($user_id));
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'add':
                echo json_encode($budgets->addBudget($input));
                break;
            case 'update':
                echo json_encode($budgets->updateBudget($input));
                break;
            case 'delete':
                echo json_encode($budgets->deleteBudget($input['id'] ?? null));
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
