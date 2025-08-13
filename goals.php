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

class Goals {
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

    public function getGoals($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM goals WHERE user_id = ? ORDER BY deadline ASC, created_at DESC");
            $stmt->execute([$user_id]);
            $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($goals as &$goal) {
                $goal['target_amount'] = (float)$goal['target_amount'];
                $goal['progress'] = (float)$goal['progress'];
                $goal['percentage'] = $goal['target_amount'] > 0 ? ($goal['progress'] / $goal['target_amount']) * 100 : 0;
                $goal['remaining'] = $goal['target_amount'] - $goal['progress'];
                
                if ($goal['deadline']) {
                    $deadline = new DateTime($goal['deadline']);
                    $today = new DateTime();
                    $goal['days_remaining'] = $today->diff($deadline)->days;
                    $goal['is_overdue'] = $deadline < $today;
                } else {
                    $goal['days_remaining'] = null;
                    $goal['is_overdue'] = false;
                }

                if ($goal['percentage'] >= 100) {
                    $goal['status'] = 'completed';
                } elseif ($goal['is_overdue']) {
                    $goal['status'] = 'overdue';
                } elseif ($goal['percentage'] >= 75) {
                    $goal['status'] = 'near_completion';
                } else {
                    $goal['status'] = 'in_progress';
                }
            }

            return $goals;
        } catch (Exception $e) {
            return false;
        }
    }

    public function addGoal($data) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            if (empty($data['title']) || empty($data['target_amount'])) {
                return ['success' => false, 'message' => 'Title and target amount are required'];
            }

            $stmt = $this->conn->prepare("INSERT INTO goals (user_id, title, target_amount, deadline, progress) VALUES (?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $user_id,
                $data['title'],
                $data['target_amount'],
                $data['deadline'] ?? null,
                $data['progress'] ?? 0
            ]);

            $goal_id = $this->conn->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Goal added successfully',
                'goal_id' => $goal_id
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to add goal: ' . $e->getMessage()];
        }
    }

    public function updateGoal($data) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            if (empty($data['id'])) {
                return ['success' => false, 'message' => 'Goal ID is required'];
            }

            $stmt = $this->conn->prepare("SELECT user_id FROM goals WHERE id = ?");
            $stmt->execute([$data['id']]);
            $goal = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$goal || (int)$goal['user_id'] != (int)$user_id) {
                return ['success' => false, 'message' => 'Goal not found or unauthorized'];
            }

            $stmt = $this->conn->prepare("UPDATE goals SET title = ?, target_amount = ?, deadline = ?, progress = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([
                $data['title'],
                $data['target_amount'],
                $data['deadline'] ?? null,
                $data['progress'] ?? 0,
                $data['id'],
                $user_id
            ]);

            return [
                'success' => true,
                'message' => 'Goal updated successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to update goal: ' . $e->getMessage()];
        }
    }

    public function deleteGoal($id) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            $stmt = $this->conn->prepare("SELECT user_id FROM goals WHERE id = ?");
            $stmt->execute([$id]);
            $goal = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$goal || (int)$goal['user_id'] != (int)$user_id) {
                return ['success' => false, 'message' => 'Goal not found or unauthorized'];
            }

            $stmt = $this->conn->prepare("DELETE FROM goals WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            return [
                'success' => true,
                'message' => 'Goal deleted successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete goal: ' . $e->getMessage()];
        }
    }

    public function updateProgress($data) {
        try {
            $user_id = $this->authenticate();
            if (!$user_id) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }

            if (empty($data['id']) || !isset($data['progress'])) {
                return ['success' => false, 'message' => 'Goal ID and progress are required'];
            }

            $stmt = $this->conn->prepare("SELECT user_id FROM goals WHERE id = ?");
            $stmt->execute([$data['id']]);
            $goal = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$goal || (int)$goal['user_id'] != (int)$user_id) {
                return ['success' => false, 'message' => 'Goal not found or unauthorized'];
            }

            $stmt = $this->conn->prepare("UPDATE goals SET progress = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$data['progress'], $data['id'], $user_id]);

            return [
                'success' => true,
                'message' => 'Progress updated successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to update progress: ' . $e->getMessage()];
        }
    }

    public function getGoalsOverview($user_id) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_goals,
                        COUNT(CASE WHEN progress >= target_amount THEN 1 END) as completed_goals,
                        COUNT(CASE WHEN deadline < CURDATE() AND progress < target_amount THEN 1 END) as overdue_goals,
                        SUM(target_amount) as total_target,
                        SUM(progress) as total_progress
                    FROM goals 
                    WHERE user_id = ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$user_id]);
            $overview = $stmt->fetch(PDO::FETCH_ASSOC);

            $overview['total_goals'] = (int)$overview['total_goals'];
            $overview['completed_goals'] = (int)$overview['completed_goals'];
            $overview['overdue_goals'] = (int)$overview['overdue_goals'];
            $overview['total_target'] = (float)$overview['total_target'];
            $overview['total_progress'] = (float)$overview['total_progress'];
            $overview['overall_progress'] = $overview['total_target'] > 0 ? ($overview['total_progress'] / $overview['total_target']) * 100 : 0;

            return $overview;
        } catch (Exception $e) {
            return false;
        }
    }
}

$goals = new Goals();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user_id = $goals->authenticate();
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    if (isset($_GET['overview'])) {
        echo json_encode($goals->getGoalsOverview($user_id));
    } else {
        echo json_encode($goals->getGoals($user_id));
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'add':
                echo json_encode($goals->addGoal($input));
                break;
            case 'update':
                echo json_encode($goals->updateGoal($input));
                break;
            case 'delete':
                echo json_encode($goals->deleteGoal($input['id'] ?? null));
                break;
            case 'update_progress':
                echo json_encode($goals->updateProgress($input));
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
