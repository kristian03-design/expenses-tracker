<?php
require_once __DIR__ . '/../config/app.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    private $config;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/app.php';
        $this->host = $this->config['database']['host'];
        $this->db_name = $this->config['database']['name'];
        $this->username = $this->config['database']['username'];
        $this->password = $this->config['database']['password'];
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->config['database']['charset'];
            $this->conn = new PDO($dsn, $this->username, $this->password, $this->config['database']['options']);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }

    public function createTables() {
        try {
            $conn = $this->getConnection();
            
            // Users table
            $conn->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                pin VARCHAR(10),
                currency VARCHAR(3) DEFAULT 'PHP',
                timezone VARCHAR(50) DEFAULT 'UTC',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");

            // Categories table
            $conn->exec("CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                category_name VARCHAR(255) NOT NULL,
                color VARCHAR(7) DEFAULT '#3B82F6',
                icon VARCHAR(50) DEFAULT 'tag',
                type ENUM('expense', 'income', 'both') DEFAULT 'both',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");

            // Transactions table
            $conn->exec("CREATE TABLE IF NOT EXISTS transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type ENUM('expense', 'income') NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'PHP',
                category_id INT,
                description TEXT,
                date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
            )");

            // Budgets table
            $conn->exec("CREATE TABLE IF NOT EXISTS budgets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                category_id INT,
                amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'PHP',
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
            )");

            // Goals table
            $conn->exec("CREATE TABLE IF NOT EXISTS goals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                target_amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'PHP',
                progress DECIMAL(10,2) DEFAULT 0,
                deadline DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");

            // Bills table
            $conn->exec("CREATE TABLE IF NOT EXISTS bills (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'PHP',
                due_date DATE NOT NULL,
                status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
                recurring ENUM('none', 'monthly', 'quarterly', 'yearly') DEFAULT 'none',
                category_id INT,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
            )");

            // User tokens table
            $conn->exec("CREATE TABLE IF NOT EXISTS user_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(255) UNIQUE NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");

            // Exchange rates table (for caching)
            $conn->exec("CREATE TABLE IF NOT EXISTS exchange_rates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                base_currency VARCHAR(3) NOT NULL,
                target_currency VARCHAR(3) NOT NULL,
                rate DECIMAL(15,6) NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_rate (base_currency, target_currency)
            )");

            echo "Tables created successfully\n";
            return true;
        } catch(PDOException $exception) {
            echo "Error creating tables: " . $exception->getMessage() . "\n";
            return false;
        }
    }

    public function insertSampleData($conn) {
        try {
            // Insert sample user
            $password_hash = password_hash('password123', PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, currency) VALUES (?, ?, ?, ?)");
            $stmt->execute(['John Doe', 'john@example.com', $password_hash, 'PHP']);
            $user_id = $conn->lastInsertId();

            // Insert sample categories
            $categories = [
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

            $stmt = $conn->prepare("INSERT INTO categories (user_id, category_name, color, icon) VALUES (?, ?, ?, ?)");
            foreach ($categories as $category) {
                $stmt->execute([$user_id, $category[0], $category[1], $category[2]]);
            }

            // Insert sample transactions
            $transactions = [
                ['expense', 25.50, 'PHP', 1, 'Lunch at restaurant', '2024-01-15'],
                ['expense', 45.00, 'PHP', 2, 'Gas for car', '2024-01-14'],
                ['expense', 120.00, 'PHP', 3, 'New clothes', '2024-01-13'],
                ['income', 2500.00, 'PHP', 9, 'Monthly salary', '2024-01-01'],
                ['expense', 89.99, 'PHP', 4, 'Electricity bill', '2024-01-10']
            ];

            $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, currency, category_id, description, date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($transactions as $transaction) {
                $stmt->execute([$user_id, $transaction[0], $transaction[1], $transaction[2], $transaction[3], $transaction[4], $transaction[5]]);
            }

            // Insert sample budget
            $stmt = $conn->prepare("INSERT INTO budgets (user_id, amount, currency, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, 2000.00, 'PHP', '2024-01-01', '2024-01-31']);

            // Insert sample goal
            $stmt = $conn->prepare("INSERT INTO goals (user_id, title, target_amount, currency, progress) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, 'Emergency Fund', 10000.00, 'PHP', 2500.00]);

            // Insert sample bill
            $stmt = $conn->prepare("INSERT INTO bills (user_id, title, amount, currency, due_date, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, 'Rent Payment', 1200.00, 'PHP', '2024-02-01', 'pending']);

            echo "Sample data inserted successfully\n";
            return true;
        } catch(PDOException $exception) {
            echo "Error inserting sample data: " . $exception->getMessage() . "\n";
            return false;
        }
    }
}
?>
