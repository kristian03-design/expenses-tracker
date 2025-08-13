<?php
// Application Configuration
return [
    // App Settings
    'app_name' => 'Expense Tracker',
    'app_version' => '1.0.0',
    'app_url' => 'http://localhost/Expenses-Tracker',
    'timezone' => 'UTC',
    
    // Database Settings
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'expenses-tracker',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],
    
    // Security Settings
    'security' => [
        'password_min_length' => 8,
        'token_expiry_days' => 30,
        'max_login_attempts' => 5,
        'lockout_duration' => 15, // minutes
        'bcrypt_cost' => 12,
        'session_timeout' => 3600, // seconds
    ],
    
    // API Settings
    'api' => [
        'rate_limit' => 100, // requests per hour
        'cors_origins' => ['*'],
        'max_request_size' => '10MB',
    ],
    
    // Default Categories
    'default_categories' => [
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
    ],
    
    // Currency Settings
    'currency' => [
        'default' => 'PHP',
        'supported' => [
            'USD' => [
                'name' => 'US Dollar',
                'symbol' => '$',
                'position' => 'before',
                'decimals' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.'
            ],
            'EUR' => [
                'name' => 'Euro',
                'symbol' => '€',
                'position' => 'before',
                'decimals' => 2,
                'thousands_separator' => '.',
                'decimal_separator' => ','
            ],
            'GBP' => [
                'name' => 'British Pound',
                'symbol' => '£',
                'position' => 'before',
                'decimals' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.'
            ],
            'JPY' => [
                'name' => 'Japanese Yen',
                'symbol' => '¥',
                'position' => 'before',
                'decimals' => 0,
                'thousands_separator' => ',',
                'decimal_separator' => '.'
            ],
            'CAD' => [
                'name' => 'Canadian Dollar',
                'symbol' => 'C$',
                'position' => 'before',
                'decimals' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.'
            ],
            'AUD' => [
                'name' => 'Australian Dollar',
                'symbol' => 'A$',
                'position' => 'before',
                'decimals' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.'
            ],
            'CHF' => [
                'name' => 'Swiss Franc',
                'symbol' => 'CHF',
                'position' => 'after',
                'decimals' => 2,
                'thousands_separator' => "'",
                'decimal_separator' => '.'
            ],
            'CNY' => [
                'name' => 'Chinese Yuan',
                'symbol' => '¥',
                'position' => 'before',
                'decimals' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.'
            ],
            'INR' => [
                'name' => 'Indian Rupee',
                'symbol' => '₹',
                'position' => 'before',
                'decimals' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.'
            ],
            'PHP' => [
                'name' => 'Philippine Peso',
                'symbol' => '₱',
                'position' => 'before',
                'decimals' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.'
            ]
        ],
        'exchange_rate_api' => [
            'enabled' => true,
            'url' => 'https://api.exchangerate-api.com/v4/latest/',
            'api_key' => '', // Add your API key here if required
            'update_interval' => 3600, // Update rates every hour
            'fallback_rates' => [
                'USD' => 1.0,
                'EUR' => 0.85,
                'GBP' => 0.73,
                'JPY' => 110.0,
                'CAD' => 1.25,
                'AUD' => 1.35,
                'CHF' => 0.92,
                'CNY' => 6.45,
                'INR' => 74.0,
                'PHP' => 50.0
            ]
        ]
    ],
    
    // Date Formats
    'date_formats' => [
        'display' => 'M j, Y',
        'database' => 'Y-m-d',
        'time' => 'g:i A',
        'datetime' => 'M j, Y g:i A'
    ],
    
    // Pagination
    'pagination' => [
        'per_page' => 20,
        'max_pages' => 100
    ],
    
    // File Upload Settings
    'uploads' => [
        'max_size' => '5MB',
        'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf'],
        'path' => 'uploads/',
        'temp_path' => 'temp/'
    ],
    
    // Notification Settings
    'notifications' => [
        'email' => [
            'enabled' => false,
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'from_email' => 'noreply@expensetracker.com',
            'from_name' => 'Expense Tracker'
        ],
        'push' => [
            'enabled' => false,
            'vapid_public_key' => '',
            'vapid_private_key' => ''
        ]
    ],
    
    // Cache Settings
    'cache' => [
        'enabled' => true,
        'driver' => 'file', // file, redis, memcached
        'ttl' => 3600, // seconds
        'path' => 'cache/'
    ],
    
    // Debug Settings
    'debug' => [
        'enabled' => true,
        'log_errors' => true,
        'display_errors' => false,
        'log_path' => 'logs/'
    ]
];
?>
