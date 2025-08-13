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

class Bills {
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

    public function getBills($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM bills WHERE user_id = ? ORDER BY due_date ASC");
            $stmt->execute([$user_id]);
            $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($bills as &$bill) {
                $bill['amount'] = (float)$bill['amount'];
                $due_date = new DateTime($bill['due_date']);
                $today = new DateTime();
                $bill['days_until_due'] = $today->diff($due_date)->days;
                $bill['is_overdue'] = $due_date < $today && $bill['status'] !== 'paid';
                
                if ($bill['is_overdue'] && $bill['status'] === 'pending') {
                    $this->updateBillStatus($bill['id'], 'overdue');
                    $bill['status'] = 'overdue';
                }
            }

            return $bills;
        } catch (Exception $e) {
            return false;
        }
    }

    public function addBill($data) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            if (empty($data['title']) || empty($data['amount']) || empty($data['due_date'])) {
                return ['success' => false, 'message' => 'Title, amount, and due date are required'];
            }

            $stmt = $this->conn->prepare("INSERT INTO bills (user_id, title, amount, due_date, status, recurring, recurring_interval) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $user_id,
                $data['title'],
                $data['amount'],
                $data['due_date'],
                $data['status'] ?? 'pending',
                !empty($data['recurring']) ? 1 : 0,
                $data['recurring_interval'] ?? null
            ]);

            $bill_id = $this->conn->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Bill added successfully',
                'bill_id' => $bill_id
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to add bill: ' . $e->getMessage()];
        }
    }

    public function updateBill($data) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            if (empty($data['id'])) {
                return ['success' => false, 'message' => 'Bill ID is required'];
            }

            $stmt = $this->conn->prepare("SELECT user_id FROM bills WHERE id = ?");
            $stmt->execute([$data['id']]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bill || (int)$bill['user_id'] != (int)$user_id) {
                return ['success' => false, 'message' => 'Bill not found or unauthorized'];
            }

            $stmt = $this->conn->prepare("UPDATE bills SET title = ?, amount = ?, due_date = ?, status = ?, recurring = ?, recurring_interval = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([
                $data['title'],
                $data['amount'],
                $data['due_date'],
                $data['status'] ?? 'pending',
                !empty($data['recurring']) ? 1 : 0,
                $data['recurring_interval'] ?? null,
                $data['id'],
                $user_id
            ]);

            return [
                'success' => true,
                'message' => 'Bill updated successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to update bill: ' . $e->getMessage()];
        }
    }

    public function deleteBill($id) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            $stmt = $this->conn->prepare("SELECT user_id FROM bills WHERE id = ?");
            $stmt->execute([$id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bill || (int)$bill['user_id'] != (int)$user_id) {
                return ['success' => false, 'message' => 'Bill not found or unauthorized'];
            }

            $stmt = $this->conn->prepare("DELETE FROM bills WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            return [
                'success' => true,
                'message' => 'Bill deleted successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete bill: ' . $e->getMessage()];
        }
    }

    public function updateBillStatus($id, $status) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            $stmt = $this->conn->prepare("SELECT user_id FROM bills WHERE id = ?");
            $stmt->execute([$id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bill || (int)$bill['user_id'] != (int)$user_id) {
                return ['success' => false, 'message' => 'Bill not found or unauthorized'];
            }

            $stmt = $this->conn->prepare("UPDATE bills SET status = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$status, $id, $user_id]);

            return [
                'success' => true,
                'message' => 'Bill status updated successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to update bill status: ' . $e->getMessage()];
        }
    }

    public function getUpcomingBills($user_id, $days = 30) {
        try {
            $sql = "SELECT * FROM bills 
                    WHERE user_id = ? 
                    AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                    AND status != 'paid'
                    ORDER BY due_date ASC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$user_id, $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    public function getBillsOverview($user_id) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_bills,
                        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_bills,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bills,
                        COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_bills,
                        SUM(CASE WHEN status != 'paid' THEN amount ELSE 0 END) as total_outstanding,
                        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid
                    FROM bills 
                    WHERE user_id = ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$user_id]);
            $overview = $stmt->fetch(PDO::FETCH_ASSOC);

            $overview['total_bills'] = (int)$overview['total_bills'];
            $overview['paid_bills'] = (int)$overview['paid_bills'];
            $overview['pending_bills'] = (int)$overview['pending_bills'];
            $overview['overdue_bills'] = (int)$overview['overdue_bills'];
            $overview['total_outstanding'] = (float)$overview['total_outstanding'];
            $overview['total_paid'] = (float)$overview['total_paid'];

            return $overview;
        } catch (Exception $e) {
            return false;
        }
    }

    public function markBillAsPaid($id) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            $stmt = $this->conn->prepare("SELECT user_id, recurring, recurring_interval, due_date, title, amount FROM bills WHERE id = ?");
            $stmt->execute([$id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bill || (int)$bill['user_id'] != (int)$user_id) {
                return ['success' => false, 'message' => 'Bill not found or unauthorized'];
            }

            $stmt = $this->conn->prepare("UPDATE bills SET status = 'paid' WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            if (!empty($bill['recurring']) && $bill['recurring_interval']) {
                $this->createNextRecurringBill($bill, $bill['recurring_interval']);
            }

            return [
                'success' => true,
                'message' => 'Bill marked as paid successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to mark bill as paid: ' . $e->getMessage()];
        }
    }

    private function createNextRecurringBill(array $original_bill, string $interval) {
        try {
            $next_due_date = new DateTime($original_bill['due_date']);
            switch ($interval) {
                case 'monthly':
                    $next_due_date->add(new DateInterval('P1M'));
                    break;
                case 'quarterly':
                    $next_due_date->add(new DateInterval('P3M'));
                    break;
                case 'yearly':
                    $next_due_date->add(new DateInterval('P1Y'));
                    break;
            }

            $stmt = $this->conn->prepare("INSERT INTO bills (user_id, title, amount, due_date, status, recurring, recurring_interval) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $original_bill['user_id'],
                $original_bill['title'],
                $original_bill['amount'],
                $next_due_date->format('Y-m-d'),
                'pending',
                1,
                $interval
            ]);
        } catch (Exception $e) {
            // ignore
        }
    }
}

$bills = new Bills();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user_id = $bills->authenticate();
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    if (isset($_GET['upcoming'])) {
        $days = $_GET['days'] ?? 30;
        echo json_encode($bills->getUpcomingBills($user_id, $days));
    } elseif (isset($_GET['overview'])) {
        echo json_encode($bills->getBillsOverview($user_id));
    } else {
        echo json_encode($bills->getBills($user_id));
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'add':
                echo json_encode($bills->addBill($input));
                break;
            case 'update':
                echo json_encode($bills->updateBill($input));
                break;
            case 'delete':
                echo json_encode($bills->deleteBill($input['id'] ?? null));
                break;
            case 'mark_paid':
                echo json_encode($bills->markBillAsPaid($input['id'] ?? null));
                break;
            case 'update_status':
                echo json_encode($bills->updateBillStatus($input['id'] ?? null, $input['status'] ?? 'pending'));
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
