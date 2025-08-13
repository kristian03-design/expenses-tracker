<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../lib/Auth.php';
require_once '../lib/Currency.php';

class Transactions {
    private $conn;
    private $auth;
    private $currency;

    public function __construct() {
        $this->auth = new Auth();
        $this->conn = $this->auth->getConnection();
        $this->currency = new Currency();
    }

    public function authenticate() {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        if (!$token && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        }
        if (!$token && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
        if (!$token) {
            return false;
        }
        return $this->auth->verifyToken($token);
    }

    private function getUserCurrency(string $user_id): string {
        try {
            $stmt = $this->conn->prepare("SELECT currency FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['currency'])) {
                return $row['currency'];
            }
        } catch (Exception $e) {
            // ignore and fallback
        }
        return 'PHP';
    }

    public function getTransactions($user_id, $filters = []) {
        try {
            $sql = "SELECT t.*, c.category_name, c.color, c.icon 
                    FROM transactions t 
                    LEFT JOIN categories c ON t.category_id = c.id 
                    WHERE t.user_id = ?";
            $params = [$user_id];

            if (!empty($filters['type'])) { $sql .= " AND t.type = ?"; $params[] = $filters['type']; }
            if (!empty($filters['category_id'])) { $sql .= " AND t.category_id = ?"; $params[] = $filters['category_id']; }
            if (!empty($filters['start_date'])) { $sql .= " AND t.date >= ?"; $params[] = $filters['start_date']; }
            if (!empty($filters['end_date'])) { $sql .= " AND t.date <= ?"; $params[] = $filters['end_date']; }

            $sql .= " ORDER BY t.date DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $userCurrency = $this->getUserCurrency($user_id);
            foreach ($transactions as &$transaction) {
                $txCurrency = $transaction['currency'] ?: $userCurrency;
                $transaction['formatted_amount'] = $this->currency->format((float)$transaction['amount'], $txCurrency);
            }
            return $transactions;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function addTransaction($user_id, $data) {
        try {
            $sql = "INSERT INTO transactions (user_id, type, amount, currency, category_id, description, date) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $currency = $data['currency'] ?? $this->getUserCurrency($user_id);
            $stmt->execute([
                $user_id,
                $data['type'] ?? 'expense',
                $data['amount'],
                $currency,
                $data['category_id'] ?? null,
                $data['description'] ?? '',
                $data['date']
            ]);

            $transaction_id = $this->conn->lastInsertId();
            $stmt = $this->conn->prepare("SELECT t.*, c.category_name, c.color, c.icon FROM transactions t LEFT JOIN categories c ON t.category_id = c.id WHERE t.id = ?");
            $stmt->execute([$transaction_id]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            $transaction['formatted_amount'] = $this->currency->format((float)$transaction['amount'], $transaction['currency']);

            return ['success' => true, 'message' => 'Transaction added successfully', 'transaction' => $transaction];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to add transaction: ' . $e->getMessage()];
        }
    }

    public function updateTransaction($user_id, $transaction_id, $data) {
        try {
            $sql = "UPDATE transactions SET type = ?, amount = ?, currency = ?, category_id = ?, description = ?, date = ? WHERE id = ? AND user_id = ?";
            $stmt = $this->conn->prepare($sql);
            $currency = $data['currency'] ?? $this->getUserCurrency($user_id);
            $stmt->execute([
                $data['type'] ?? 'expense',
                $data['amount'],
                $currency,
                $data['category_id'] ?? null,
                $data['description'] ?? '',
                $data['date'],
                $transaction_id,
                $user_id
            ]);
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Transaction updated successfully'];
            }
            return ['success' => false, 'message' => 'Transaction not found or no changes made'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to update transaction: ' . $e->getMessage()];
        }
    }

    public function deleteTransaction($user_id, $transaction_id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
            $stmt->execute([$transaction_id, $user_id]);
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Transaction deleted successfully'];
            }
            return ['success' => false, 'message' => 'Transaction not found'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete transaction: ' . $e->getMessage()];
        }
    }

    public function getTransactionStats($user_id, $filters = []) {
        try {
            $sql = "SELECT 
                        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expenses,
                        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                        COUNT(*) as total_transactions
                    FROM transactions 
                    WHERE user_id = ?";
            $params = [$user_id];
            if (!empty($filters['start_date'])) { $sql .= " AND date >= ?"; $params[] = $filters['start_date']; }
            if (!empty($filters['end_date'])) { $sql .= " AND date <= ?"; $params[] = $filters['end_date']; }
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_expenses' => 0, 'total_income' => 0, 'total_transactions' => 0];

            $userCurrency = $this->getUserCurrency($user_id);
            $stats['formatted_total_expenses'] = $this->currency->format((float)$stats['total_expenses'], $userCurrency);
            $stats['formatted_total_income'] = $this->currency->format((float)$stats['total_income'], $userCurrency);
            return $stats;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

$transactions = new Transactions();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user_id = $transactions->authenticate();
    if (!$user_id) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

    $filters = [];
    foreach (['type','category_id','start_date','end_date'] as $k) { if (isset($_GET[$k])) { $filters[$k] = $_GET[$k]; } }

    if (isset($_GET['stats'])) {
        $result = $transactions->getTransactionStats($user_id, $filters);
    } else {
        $result = $transactions->getTransactions($user_id, $filters);
    }
    echo json_encode($result);
} elseif ($method === 'POST') {
    $user_id = $transactions->authenticate();
    if (!$user_id) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    if ($action === 'add') {
        $result = $transactions->addTransaction($user_id, $input);
    } elseif ($action === 'update') {
        $txId = $input['id'] ?? null; if (!$txId) { echo json_encode(['success'=>false,'message'=>'Transaction ID required']); exit; }
        $result = $transactions->updateTransaction($user_id, $txId, $input);
    } elseif ($action === 'delete') {
        $txId = $input['id'] ?? null; if (!$txId) { echo json_encode(['success'=>false,'message'=>'Transaction ID required']); exit; }
        $result = $transactions->deleteTransaction($user_id, $txId);
    } else {
        $result = ['success' => false, 'message' => 'Invalid action'];
    }
    echo json_encode($result);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
