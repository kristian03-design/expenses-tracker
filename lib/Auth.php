<?php
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $conn;
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    public function register($data) {
        try {
            if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
                return ['success' => false, 'message' => 'All fields are required'];
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }

            $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'User already exists'];
            }

            $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
            $pin = isset($data['pin']) ? $data['pin'] : null;
            $stmt = $this->conn->prepare("INSERT INTO users (name, email, password_hash, pin) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['name'], $data['email'], $password_hash, $pin]);
            
            $user_id = $this->conn->lastInsertId();
            $this->createDefaultCategories($user_id);

            return [
                'success' => true,
                'message' => 'User registered successfully',
                'user_id' => $user_id
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    public function login($data) {
        try {
            if (empty($data['email']) || empty($data['password'])) {
                return ['success' => false, 'message' => 'Email and password are required'];
            }

            $stmt = $this->conn->prepare("SELECT id, name, email, password_hash, pin FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }

            if (!password_verify($data['password'], $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }

            $token = $this->generateToken();
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
            $stmt = $this->conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expires_at]);

            unset($user['password_hash']);
            $user['token'] = $token;

            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => $user
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }

    public function verifyToken($token) {
        try {
            $stmt = $this->conn->prepare("SELECT user_id FROM user_tokens WHERE token = ? AND expires_at > NOW()");
            $stmt->execute([$token]);
            if ($stmt->rowCount() > 0) {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return (int)$result['user_id'];
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    private function generateToken() {
        return bin2hex(random_bytes(32));
    }

    private function createDefaultCategories($user_id) {
        $default_categories = [
            ['Food & Dining', '#EF4444', 'utensils'],
            ['Transportation', '#3B82F6', 'car'],
            ['Shopping', '#8B5CF6', 'shopping-bag'],
            ['Bills & Utilities', '#10B981', 'file-text'],
            ['Entertainment', '#F59E0B', 'film'],
            ['Healthcare', '#EC4899', 'heart'],
            ['Education', '#06B6D4', 'book'],
            ['Travel', '#84CC16', 'plane'],
            ['Salary', '#10B981', 'dollar-sign'],
            ['Freelance', '#8B5CF6', 'briefcase']
        ];

        $stmt = $this->conn->prepare("INSERT INTO categories (user_id, category_name, color, icon) VALUES (?, ?, ?, ?)");
        foreach ($default_categories as $category) {
            $stmt->execute([$user_id, $category[0], $category[1], $category[2]]);
        }
    }
}
