# Expense Tracker - Smart Financial Management

A modern, responsive, and user-friendly web-based Expense Tracker with PHP + MySQL backend, featuring comprehensive financial management tools, beautiful charts, and offline capabilities.

## 🚀 Features

### Core Functionality
- **Transaction Management**: Add, edit, delete expenses and income
- **Smart Categorization**: Pre-built categories with custom colors and icons
- **Budget Tracking**: Set budgets per category or overall with progress indicators
- **Financial Goals**: Track savings goals with deadlines and progress
- **Bill Reminders**: Manage recurring bills and payment tracking
- **Multi-currency Support**: Ready for international use

### Analytics & Insights
- **Real-time Dashboard**: Live overview of financial status
- **Interactive Charts**: Category breakdown and monthly trends using Chart.js
- **Smart Reports**: Daily, weekly, monthly, and yearly financial summaries
- **Progress Tracking**: Visual indicators for budgets and goals

### Security & User Management
- **Secure Authentication**: Bcrypt password hashing and JWT tokens
- **User Sessions**: Persistent login with secure token storage
- **Data Privacy**: User data isolation and secure API endpoints
- **SQL Injection Prevention**: Prepared statements and input validation

### Modern UI/UX
- **Responsive Design**: Works perfectly on desktop, tablet, and mobile
- **TailwindCSS**: Modern, utility-first CSS framework
- **Smooth Animations**: Elegant transitions and micro-interactions
- **Dark Mode Ready**: Built with modern design principles
- **Font Awesome Icons**: Beautiful, consistent iconography

## 🛠️ Technology Stack

### Backend
- **PHP 7.4+**: Modern PHP with PDO database abstraction
- **MySQL 5.7+**: Robust relational database
- **RESTful API**: Clean, stateless API design
- **JWT Authentication**: Secure token-based authentication

### Frontend
- **HTML5**: Semantic markup
- **TailwindCSS**: Utility-first CSS framework
- **Vanilla JavaScript**: Modern ES6+ without framework dependencies
- **Chart.js**: Beautiful, responsive charts
- **Font Awesome**: Professional icon library

### Development Tools
- **XAMPP**: Local development environment
- **Git**: Version control
- **Modern Browser Support**: Chrome, Firefox, Safari, Edge

## 📋 Prerequisites

Before you begin, ensure you have the following installed:
- **XAMPP** (Apache + MySQL + PHP) or similar local server stack
- **PHP 7.4** or higher
- **MySQL 5.7** or higher
- **Modern web browser** with JavaScript enabled

## 🚀 Installation & Setup

### 1. Clone the Repository
```bash
git clone <repository-url>
cd Expenses-Tracker
```

### 2. Database Setup
1. **Start XAMPP**: Launch XAMPP Control Panel and start Apache and MySQL services
2. **Create Database**: Open phpMyAdmin and create a new database named `expense_tracker`
3. **Configure Database**: Update database credentials in `config/database.php` if needed:
   ```php
   private $host = 'localhost';
   private $db_name = 'expense_tracker';
   private $username = 'root';
   private $password = '';
   ```

### 3. Run Setup Script
1. **Access Setup**: Open your browser and navigate to `http://localhost/Expenses-Tracker/setup.php`
2. **Verify Setup**: The script will create all necessary tables and insert sample data
3. **Check Output**: Ensure all setup steps show green checkmarks

### 4. Access the Application
1. **Open Application**: Navigate to `http://localhost/Expenses-Tracker/src/index.html`
2. **Login**: Use the default demo credentials:
   - **Email**: `demo@example.com`
   - **Password**: `password123`

## 🎯 Default Sample Data

The setup script creates:
- **Demo User**: Sample account for testing
- **10 Categories**: Pre-built expense and income categories
- **Sample Transactions**: Various transactions across different categories
- **Sample Budget**: Monthly food budget example
- **Sample Goal**: Emergency fund savings goal
- **Sample Bill**: Monthly rent reminder

## 📱 Usage Guide

### Getting Started
1. **Login/Register**: Create a new account or use demo credentials
2. **Dashboard Overview**: View financial summary and quick stats
3. **Add Transactions**: Use quick action buttons to log expenses/income
4. **Set Budgets**: Create spending limits for categories or overall
5. **Track Goals**: Set and monitor financial objectives

### Key Features
- **Quick Actions**: Fast access to common tasks
- **Real-time Updates**: Dashboard refreshes automatically
- **Smart Categorization**: Automatic category suggestions
- **Progress Tracking**: Visual indicators for budgets and goals
- **Bill Management**: Never miss a payment with smart reminders

### Mobile Experience
- **Responsive Design**: Optimized for all screen sizes
- **Touch-Friendly**: Large buttons and intuitive gestures
- **Offline Capable**: Works without internet connection
- **Fast Loading**: Optimized performance for mobile devices

## 🔧 API Endpoints

### Authentication
- `POST /api/auth.php` - User registration and login

### Transactions
- `GET /api/transactions.php` - Fetch user transactions
- `POST /api/transactions.php` - Add/edit/delete transactions
- `GET /api/transactions.php?stats=1` - Get transaction statistics

### Categories
- `GET /api/categories.php` - Fetch user categories
- `POST /api/categories.php` - Manage categories

### Budgets
- `GET /api/budgets.php` - Fetch user budgets
- `POST /api/budgets.php` - Manage budgets
- `GET /api/budgets.php?overview=1` - Get budget overview

### Goals
- `GET /api/goals.php` - Fetch user goals
- `POST /api/goals.php` - Manage goals
- `GET /api/goals.php?overview=1` - Get goals overview

### Bills
- `GET /api/bills.php` - Fetch user bills
- `POST /api/bills.php` - Manage bills
- `GET /api/bills.php?upcoming=1` - Get upcoming bills

## 🎨 Customization

### Adding New Categories
1. **Database**: Add new category in `categories` table
2. **Frontend**: Categories automatically appear in dropdowns
3. **Colors**: Customize category colors in the database

### Modifying Default Categories
- Edit the `createDefaultCategories()` method in `api/auth.php`
- Update category names, colors, and icons as needed

### Styling Changes
- **TailwindCSS**: Modify `src/input.css` for custom styles
- **Theme Colors**: Update color scheme in CSS variables
- **Responsive Breakpoints**: Adjust mobile/tablet breakpoints

## 🔒 Security Features

- **Password Hashing**: Bcrypt with secure salt
- **SQL Injection Prevention**: Prepared statements throughout
- **Token Authentication**: JWT-based session management
- **Input Validation**: Server-side validation for all inputs
- **CORS Protection**: Proper cross-origin request handling
- **User Isolation**: Data separation between users

## 📊 Database Schema

### Core Tables
- **users**: User accounts and authentication
- **categories**: Transaction categories with colors and icons
- **transactions**: Financial transactions with metadata
- **budgets**: Spending limits and budget tracking
- **goals**: Financial goals and progress
- **bills**: Bill reminders and recurring payments
- **user_tokens**: Authentication tokens and sessions

### Relationships
- All user data is properly linked with foreign keys
- Cascading deletes ensure data integrity
- Indexed fields for optimal performance

## 🚀 Deployment

### Local Development
- Use XAMPP for local development
- Access via `http://localhost/Expenses-Tracker/`
- Database runs on local MySQL instance

### Production Deployment
1. **Upload Files**: Transfer all files to web server
2. **Database Setup**: Create production database
3. **Configuration**: Update database credentials
4. **Permissions**: Ensure proper file permissions
5. **SSL**: Enable HTTPS for production use

### Server Requirements
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Web Server**: Apache/Nginx with PHP support
- **SSL Certificate**: Recommended for production

## 🐛 Troubleshooting

### Common Issues
1. **Database Connection Error**: Check XAMPP services and credentials
2. **API 500 Errors**: Verify PHP error logs and file permissions
3. **Charts Not Loading**: Ensure Chart.js CDN is accessible
4. **Login Issues**: Clear browser cache and localStorage

### Debug Mode
- Enable PHP error reporting in development
- Check browser console for JavaScript errors
- Verify API responses in Network tab

## 🔮 Future Enhancements

### Planned Features
- **Dark Mode**: Theme switching capability
- **Export Functionality**: CSV, Excel, PDF export
- **Receipt Scanner**: OCR integration for receipts
- **Multi-language Support**: Internationalization
- **Advanced Analytics**: Machine learning insights
- **Mobile App**: React Native companion app

### API Extensions
- **Webhook Support**: Real-time notifications
- **Bulk Operations**: Batch transaction import
- **Advanced Filtering**: Complex search queries
- **Data Export**: Comprehensive data export APIs

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

### Development Setup
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📞 Support

- **Issues**: Report bugs via GitHub Issues
- **Documentation**: Check this README and inline code comments
- **Community**: Join our discussion forum

## 🙏 Acknowledgments

- **TailwindCSS**: Beautiful utility-first CSS framework
- **Chart.js**: Amazing charting library
- **Font Awesome**: Professional icon collection
- **PHP Community**: Excellent documentation and examples

---

**Built with ❤️ for better financial management**

*Start tracking your expenses today and take control of your financial future!*
