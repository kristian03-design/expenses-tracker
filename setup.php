<?php
require_once 'config/database.php';

echo "<h1>Expense Tracker Database Setup</h1>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "<p style='color: green;'>✓ Database connection successful</p>";
        
        if ($db->createTables()) {
            echo "<p style='color: green;'>✓ Database tables created successfully</p>";
            
            // Insert sample data for testing
            insertSampleData($conn);
            
        } else {
            echo "<p style='color: red;'>✗ Failed to create tables</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Database connection failed</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

function insertSampleData($conn) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            echo "<p style='color: blue;'>ℹ Sample data already exists</p>";
            return;
        }
        
        $password_hash = password_hash('password123', PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute(['Demo User', 'demo@example.com', $password_hash]);
        $user_id = $conn->lastInsertId();
        
        echo "<p style='color: green;'>✓ Sample user created (demo@example.com / password123)</p>";
        
        $sample_categories = [
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
        foreach ($sample_categories as $category) {
            $stmt->execute([$user_id, $category[0], $category[1], $category[2]]);
        }
        
        echo "<p style='color: green;'>✓ Sample categories created</p>";
        
        $sample_transactions = [
            [50.00, 1, '2024-01-15', 'Lunch at restaurant', 'expense'],
            [25.00, 2, '2024-01-15', 'Bus fare', 'expense'],
            [100.00, 3, '2024-01-14', 'Groceries', 'expense'],
            [80.00, 4, '2024-01-10', 'Electricity bill', 'expense'],
            [2000.00, 9, '2024-01-01', 'Monthly salary', 'income'],
            [150.00, 5, '2024-01-12', 'Movie tickets', 'expense'],
            [75.00, 6, '2024-01-08', 'Doctor visit', 'expense'],
            [300.00, 7, '2024-01-05', 'Online course', 'expense'],
            [500.00, 10, '2024-01-20', 'Freelance project', 'income']
        ];
        
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, category_id, date, description, type) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($sample_transactions as $transaction) {
            $stmt->execute([$user_id, $transaction[0], $transaction[1], $transaction[2], $transaction[3], $transaction[4]]);
        }
        
        echo "<p style='color: green;'>✓ Sample transactions created</p>";
        
        $stmt = $conn->prepare("INSERT INTO budgets (user_id, category_id, amount, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, 1, 300.00, '2024-01-01', '2024-01-31']);
        
        echo "<p style='color: green;'>✓ Sample budget created</p>";
        
        $stmt = $conn->prepare("INSERT INTO goals (user_id, title, target_amount, deadline, progress) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, 'Emergency Fund', 5000.00, '2024-12-31', 1500.00]);
        
        echo "<p style='color: green;'>✓ Sample goal created</p>";
        
        $stmt = $conn->prepare("INSERT INTO bills (user_id, title, amount, due_date, status, recurring) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, 'Rent', 1200.00, '2024-02-01', 'pending', true]);
        
        echo "<p style='color: green;'>✓ Sample bill created</p>";
        
        echo "<p style='color: green;'>✓ All sample data inserted successfully!</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error inserting sample data: " . $e->getMessage() . "</p>";
    }
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    background-color: #f5f5f5;
}

h1 {
    color: #333;
    text-align: center;
}

p {
    padding: 10px;
    margin: 5px 0;
    border-radius: 5px;
    background-color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.instructions {
    background-color: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 15px;
    margin: 20px 0;
}

.instructions h3 {
    margin-top: 0;
    color: #1976d2;
}

.instructions ul {
    margin: 10px 0;
    padding-left: 20px;
}

.instructions li {
    margin: 5px 0;
}
</style>

<div class="instructions">
    <h3>Setup Instructions:</h3>
    <ol>
        <li>Make sure you have XAMPP running with Apache and MySQL services started</li>
        <li>Create a database (update `config/database.php` to match). Current: <code><?php echo htmlspecialchars((new Database())->getConnection() ? 'connected' : 'not connected'); ?></code></li>
        <li>Run this setup script by visiting it in your browser</li>
        <li>After successful setup, you can access the application at <code>src/index.html</code></li>
    </ol>
    
    <h3>Default Login Credentials:</h3>
    <ul>
        <li><strong>Email:</strong> demo@example.com</li>
        <li><strong>Password:</strong> password123</li>
    </ul>
</div>

<div class="instructions">
    <h3>Next Steps:</h3>
    <ol>
        <li>Open <code>src/index.html</code> in your browser</li>
        <li>Login with the demo credentials above</li>
        <li>Explore the application features</li>
        <li>Customize categories, budgets, and goals as needed</li>
    </ol>
</div>
