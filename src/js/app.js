class ExpenseTracker {
    constructor() {
        this.apiBase = '../api';
        this.user = null;
        this.token = null;
        this.categories = [];
        this.transactions = [];
        this.budgets = [];
        this.goals = [];
        this.bills = [];
        this.currentCurrency = 'PHP';
        this.currencySymbols = {
            'USD': '$', 'EUR': '€', 'GBP': '£', 'JPY': '¥', 'CAD': 'C$',
            'AUD': 'A$', 'CHF': 'CHF', 'CNY': '¥', 'INR': '₹', 'PHP': '₱'
        };
        
        this.init();
    }

    async init() {
        document.getElementById('loadingScreen').style.display = 'none';
        
        this.token = localStorage.getItem('expense_tracker_token');
        if (this.token) {
            await this.loadUserData();
            this.showApp();
        } else {
            this.showAuth();
        }
        
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Auth
        document.getElementById('loginFormElement').addEventListener('submit', (e) => this.handleLogin(e));
        document.getElementById('registerFormElement').addEventListener('submit', (e) => this.handleRegister(e));
        document.getElementById('showRegisterBtn').addEventListener('click', () => this.toggleAuthForms());
        document.getElementById('showLoginBtn').addEventListener('click', () => this.toggleAuthForms());
        document.getElementById('logoutBtn').addEventListener('click', () => this.logout());

        // Quick actions
        document.getElementById('addExpenseBtn').addEventListener('click', () => this.showTransactionModal('expense'));
        document.getElementById('addIncomeBtn').addEventListener('click', () => this.showTransactionModal('income'));
        document.getElementById('addBudgetBtn').addEventListener('click', () => this.showBudgetModal());
        document.getElementById('addGoalBtn').addEventListener('click', () => this.showGoalModal());

        // Modals
        document.getElementById('transactionForm').addEventListener('submit', (e) => this.handleTransactionSubmit(e));
        document.getElementById('budgetForm').addEventListener('submit', (e) => this.handleBudgetSubmit(e));
        document.getElementById('goalForm').addEventListener('submit', (e) => this.handleGoalSubmit(e));
        document.getElementById('cancelTransactionBtn').addEventListener('click', () => this.hideTransactionModal());
        document.getElementById('cancelBudgetBtn').addEventListener('click', () => this.hideBudgetModal());
        document.getElementById('cancelGoalBtn').addEventListener('click', () => this.hideGoalModal());

        // Click outside to close modals
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('fixed')) {
                this.hideAllModals();
            }
        });

        // Navigation
        document.getElementById('navDashboard').addEventListener('click', () => this.showView('dashboard'));
        document.getElementById('navTransactions').addEventListener('click', () => this.showView('transactions'));
        document.getElementById('navBudgets').addEventListener('click', () => this.showView('budgets'));
        document.getElementById('navGoals').addEventListener('click', () => this.showView('goals'));
        document.getElementById('viewAllTransactionsBtn').addEventListener('click', () => this.showView('transactions'));
    }

    showView(name) {
        const dash = document.getElementById('dashboardView');
        const tx = document.getElementById('transactionsView');
        const bdg = document.getElementById('budgetsView');
        const gls = document.getElementById('goalsView');

        dash.classList.add('hidden');
        tx.classList.add('hidden');
        bdg.classList.add('hidden');
        gls.classList.add('hidden');

        switch (name) {
            case 'dashboard':
                dash.classList.remove('hidden');
                break;
            case 'transactions':
                tx.classList.remove('hidden');
                this.renderAllTransactions();
                break;
            case 'budgets':
                bdg.classList.remove('hidden');
                this.renderBudgets();
                break;
            case 'goals':
                gls.classList.remove('hidden');
                this.renderGoals();
                break;
        }

        // Update active nav styles
        this.updateNavActive(name);
    }

    updateNavActive(name) {
        const btns = {
            dashboard: document.getElementById('navDashboard'),
            transactions: document.getElementById('navTransactions'),
            budgets: document.getElementById('navBudgets'),
            goals: document.getElementById('navGoals')
        };
        Object.keys(btns).forEach(k => {
            if (k === name) {
                btns[k].classList.remove('bg-white', 'text-gray-800', 'border');
                btns[k].classList.add('bg-blue-600', 'text-white');
            } else {
                btns[k].classList.remove('bg-blue-600', 'text-white');
                btns[k].classList.add('bg-white', 'text-gray-800', 'border');
            }
        });
    }

    async handleLogin(e) {
        e.preventDefault();
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        try {
            const response = await fetch(`${this.apiBase}/auth.php`, {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'login', email, password })
            });
            const data = await response.json();
            if (data.success) {
                this.user = data.user;
                this.token = data.user.token;
                localStorage.setItem('expense_tracker_token', this.token);
                localStorage.setItem('expensetracker_user', JSON.stringify(this.user));
                await this.loadUserData();
                this.showApp();
                this.showNotification('Login successful!', 'success');
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch {
            this.showNotification('Login failed. Please try again.', 'error');
        }
    }

    async handleRegister(e) {
        e.preventDefault();
        const name = document.getElementById('regName').value;
        const email = document.getElementById('regEmail').value;
        const password = document.getElementById('regPassword').value;
        try {
            const response = await fetch(`${this.apiBase}/auth.php`, {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'register', name, email, password })
            });
            const data = await response.json();
            if (data.success) {
                this.showNotification('Registration successful! Please login.', 'success');
                this.toggleAuthForms();
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch {
            this.showNotification('Registration failed. Please try again.', 'error');
        }
    }

    async loadUserData() {
        try {
            const categoriesResponse = await fetch(`${this.apiBase}/categories.php`, {
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            this.categories = await categoriesResponse.json();

            const transactionsResponse = await fetch(`${this.apiBase}/transactions.php`, {
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            this.transactions = await transactionsResponse.json();

            // Budgets list
            const budgetsListResp = await fetch(`${this.apiBase}/budgets.php`, {
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            this.budgets = await budgetsListResp.json();

            // Goals list
            const goalsListResp = await fetch(`${this.apiBase}/goals.php`, {
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            this.goals = await goalsListResp.json();

            // Upcoming bills
            const billsResponse = await fetch(`${this.apiBase}/bills.php?upcoming=1&days=7`, {
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            this.bills = await billsResponse.json();

            this.updateDashboard();
            this.populateCategories();
        } catch (error) {
            console.error('Error loading user data:', error);
        }
    }

    updateDashboard() {
        document.getElementById('userName').textContent = this.user.name;
        const totalExpenses = this.transactions.filter(t => t.type === 'expense').reduce((s, t) => s + parseFloat(t.amount), 0);
        const totalIncome = this.transactions.filter(t => t.type === 'income').reduce((s, t) => s + parseFloat(t.amount), 0);
        document.getElementById('totalExpenses').textContent = `${this.currencySymbols[this.currentCurrency]}${totalExpenses.toFixed(2)}`;
        document.getElementById('totalIncome').textContent = `${this.currencySymbols[this.currentCurrency]}${totalIncome.toFixed(2)}`;
        this.updateTransactionsTable();
        this.updateUpcomingBills();
        this.updateCharts();
    }

    updateTransactionsTable() {
        const tbody = document.getElementById('transactionsTableBody');
        tbody.innerHTML = '';
        const recentTransactions = this.transactions.slice(0, 10);
        recentTransactions.forEach(transaction => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${this.formatDate(transaction.date)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${transaction.description || '-'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${transaction.category_name || 'Uncategorized'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm ${transaction.type === 'expense' ? 'text-red-600' : 'text-green-600'}">
                    ${transaction.type === 'expense' ? '-' : '+'}${this.currencySymbols[this.currentCurrency]}${parseFloat(transaction.amount).toFixed(2)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <button class="text-blue-600 hover:text-blue-900 mr-2" onclick="app.editTransaction(${transaction.id})"><i class="fas fa-edit"></i></button>
                    <button class="text-red-600 hover:text-red-900" onclick="app.deleteTransaction(${transaction.id})"><i class="fas fa-trash"></i></button>
                </td>`;
            tbody.appendChild(row);
        });
    }

    renderAllTransactions() {
        const tbody = document.getElementById('allTransactionsTableBody');
        tbody.innerHTML = '';
        this.transactions.forEach(t => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${this.formatDate(t.date)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm ${t.type === 'expense' ? 'text-red-600' : 'text-green-600'}">${t.type}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${t.category_name || 'Uncategorized'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${t.description || '-'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right ${t.type === 'expense' ? 'text-red-600' : 'text-green-600'}">${t.type === 'expense' ? '-' : '+'}${this.currencySymbols[this.currentCurrency]} ${parseFloat(t.amount).toFixed(2)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <button class="text-blue-600 hover:text-blue-900 mr-2" onclick="app.editTransaction(${t.id})"><i class="fas fa-edit"></i></button>
                    <button class="text-red-600 hover:text-red-900" onclick="app.deleteTransaction(${t.id})"><i class="fas fa-trash"></i></button>
                </td>`;
            tbody.appendChild(row);
        });
    }

    renderBudgets() {
        const container = document.getElementById('budgetsList');
        container.innerHTML = '';
        if (!Array.isArray(this.budgets) || this.budgets.length === 0) {
            container.innerHTML = '<div class="col-span-full text-gray-500">No budgets yet.</div>';
            return;
        }
        this.budgets.forEach(b => {
            const percentage = Math.min(100, Math.round((b.spent / b.amount) * 100 || 0));
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg shadow p-6';
            card.innerHTML = `
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-md font-semibold text-gray-900">${b.category_name || 'Overall Budget'}</h4>
                    <span class="text-sm text-gray-500">${this.formatDate(b.start_date)} - ${this.formatDate(b.end_date)}</span>
                </div>
                <div class="mb-2 text-sm text-gray-600">Spent: ${this.currencySymbols[this.currentCurrency]}${(b.spent || 0).toFixed(2)} / ${this.currencySymbols[this.currentCurrency]}${(b.amount || 0).toFixed(2)}</div>
                <div class="w-full bg-gray-100 rounded-full h-2.5 mb-2">
                    <div class="h-2.5 rounded-full ${percentage >= 100 ? 'bg-red-500' : percentage >= 80 ? 'bg-yellow-500' : 'bg-blue-600'}" style="width: ${percentage}%"></div>
                </div>
                <div class="text-sm ${percentage >= 100 ? 'text-red-600' : percentage >= 80 ? 'text-yellow-600' : 'text-gray-700'}">${percentage}% used</div>
            `;
            container.appendChild(card);
        });
    }

    renderGoals() {
        const container = document.getElementById('goalsList');
        container.innerHTML = '';
        if (!Array.isArray(this.goals) || this.goals.length === 0) {
            container.innerHTML = '<div class="col-span-full text-gray-500">No goals yet.</div>';
            return;
        }
        this.goals.forEach(g => {
            const percentage = Math.min(100, Math.round((g.progress / g.target_amount) * 100 || 0));
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg shadow p-6';
            card.innerHTML = `
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-md font-semibold text-gray-900">${g.title}</h4>
                    <span class="text-sm text-gray-500">${g.deadline ? this.formatDate(g.deadline) : 'No deadline'}</span>
                </div>
                <div class="mb-2 text-sm text-gray-600">Saved: ${this.currencySymbols[this.currentCurrency]}${(g.progress || 0).toFixed(2)} / ${this.currencySymbols[this.currentCurrency]}${(g.target_amount || 0).toFixed(2)}</div>
                <div class="w-full bg-gray-100 rounded-full h-2.5 mb-2">
                    <div class="h-2.5 rounded-full ${percentage >= 100 ? 'bg-green-600' : 'bg-purple-600'}" style="width: ${percentage}%"></div>
                </div>
                <div class="text-sm ${percentage >= 100 ? 'text-green-700' : 'text-gray-700'}">${percentage}% complete</div>
            `;
            container.appendChild(card);
        });
    }

    updateUpcomingBills() {
        const container = document.getElementById('upcomingBills');
        container.innerHTML = '';
        if (!this.bills || this.bills.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-sm">No upcoming bills</p>';
            return;
        }
        this.bills.forEach(bill => {
            const billElement = document.createElement('div');
            billElement.className = 'flex items-center justify-between p-3 bg-gray-50 rounded-lg';
            billElement.innerHTML = `
                <div>
                    <p class="text-sm font-medium text-gray-900">${bill.title}</p>
                    <p class="text-xs text-gray-500">Due: ${this.formatDate(bill.due_date)}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-medium text-gray-900">${this.currencySymbols[this.currentCurrency]}${parseFloat(bill.amount).toFixed(2)}</p>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                        bill.status === 'overdue' ? 'bg-red-100 text-red-800' :
                        bill.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-green-100 text-green-800'
                    }">${bill.status}</span>
                </div>`;
            container.appendChild(billElement);
        });
    }

    updateCharts() {
        this.updateCategoryChart();
        this.updateTrendChart();
    }

    updateCategoryChart() {
        const ctx = document.getElementById('categoryChart').getContext('2d');
        const categoryData = {};
        this.transactions.filter(t => t.type === 'expense').forEach(t => {
            const categoryName = t.category_name || 'Uncategorized';
            categoryData[categoryName] = (categoryData[categoryName] || 0) + parseFloat(t.amount);
        });
        const labels = Object.keys(categoryData);
        const data = Object.values(categoryData);
        const colors = this.generateColors(labels.length);
        if (window.categoryChart) window.categoryChart.destroy();
        window.categoryChart = new Chart(ctx, {
            type: 'doughnut',
            data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    }

    updateTrendChart() {
        const ctx = document.getElementById('trendChart').getContext('2d');
        const monthlyData = {};
        this.transactions.forEach(t => {
            const month = t.date.substring(0, 7);
            if (!monthlyData[month]) monthlyData[month] = { expenses: 0, income: 0 };
            if (t.type === 'expense') monthlyData[month].expenses += parseFloat(t.amount);
            else monthlyData[month].income += parseFloat(t.amount);
        });
        const months = Object.keys(monthlyData).sort();
        const expenses = months.map(m => monthlyData[m].expenses);
        const income = months.map(m => monthlyData[m].income);
        if (window.trendChart) window.trendChart.destroy();
        window.trendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: months.map(m => this.formatMonth(m)),
                datasets: [
                    { label: 'Expenses', data: expenses, borderColor: '#EF4444', backgroundColor: 'rgba(239,68,68,.1)', tension: .4 },
                    { label: 'Income', data: income, borderColor: '#10B981', backgroundColor: 'rgba(16,185,129,.1)', tension: .4 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    generateColors(count) {
        const palette = ['#EF4444','#3B82F6','#10B981','#F59E0B','#8B5CF6','#EC4899','#06B6D4','#84CC16','#F97316','#6366F1'];
        return Array.from({ length: count }, (_, i) => palette[i % palette.length]);
    }

    populateCategories() {
        const transactionCategory = document.getElementById('transactionCategory');
        const budgetCategory = document.getElementById('budgetCategory');
        transactionCategory.innerHTML = '<option value="">Select Category</option>';
        budgetCategory.innerHTML = '<option value="">Overall Budget</option>';
        this.categories.forEach(category => {
            const option1 = document.createElement('option');
            option1.value = category.id;
            option1.textContent = category.category_name;
            transactionCategory.appendChild(option1);
            const option2 = document.createElement('option');
            option2.value = category.id;
            option2.textContent = category.category_name;
            budgetCategory.appendChild(option2);
        });
    }

    showTransactionModal(type='expense') {
        document.getElementById('transactionModal').classList.remove('hidden');
        document.getElementById('transactionModalTitle').textContent = `Add ${type.charAt(0).toUpperCase()+type.slice(1)}`;
        document.getElementById('transactionType').value = type;
        document.getElementById('transactionDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('transactionForm').reset();
        document.getElementById('transactionId').value = '';
    }
    hideTransactionModal() { document.getElementById('transactionModal').classList.add('hidden'); }

    async handleTransactionSubmit(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = {
            action: 'add',
            amount: formData.get('amount'),
            category_id: formData.get('category_id') || null,
            date: formData.get('date'),
            description: formData.get('description'),
            type: formData.get('type')
        };
        try {
            const response = await fetch(`${this.apiBase}/transactions.php`, {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${this.token}` },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) {
                this.showNotification('Transaction added successfully!', 'success');
                this.hideTransactionModal();
                await this.loadUserData();
                this.renderAllTransactions();
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch {
            this.showNotification('Failed to add transaction. Please try again.', 'error');
        }
    }

    showBudgetModal() {
        document.getElementById('budgetModal').classList.remove('hidden');
        document.getElementById('budgetStartDate').value = new Date().toISOString().split('T')[0];
        const now = new Date();
        const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        document.getElementById('budgetEndDate').value = endOfMonth.toISOString().split('T')[0];
        document.getElementById('budgetForm').reset();
        document.getElementById('budgetId').value = '';
    }
    hideBudgetModal() { document.getElementById('budgetModal').classList.add('hidden'); }

    async handleBudgetSubmit(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = {
            action: 'add',
            category_id: formData.get('category_id') || null,
            amount: formData.get('amount'),
            start_date: formData.get('start_date'),
            end_date: formData.get('end_date')
        };
        try {
            const response = await fetch(`${this.apiBase}/budgets.php`, {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${this.token}` },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) {
                this.showNotification('Budget set successfully!', 'success');
                this.hideBudgetModal();
                await this.loadUserData();
                this.renderBudgets();
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch {
            this.showNotification('Failed to set budget. Please try again.', 'error');
        }
    }

    showGoalModal() {
        document.getElementById('goalModal').classList.remove('hidden');
        document.getElementById('goalForm').reset();
        document.getElementById('goalId').value = '';
        document.getElementById('goalProgress').value = '0';
    }
    hideGoalModal() { document.getElementById('goalModal').classList.add('hidden'); }

    async handleGoalSubmit(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = {
            action: 'add',
            title: formData.get('title'),
            target_amount: formData.get('target_amount'),
            deadline: formData.get('deadline') || null,
            progress: formData.get('progress')
        };
        try {
            const response = await fetch(`${this.apiBase}/goals.php`, {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${this.token}` },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) {
                this.showNotification('Goal added successfully!', 'success');
                this.hideGoalModal();
                await this.loadUserData();
                this.renderGoals();
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch {
            this.showNotification('Failed to add goal. Please try again.', 'error');
        }
    }

    hideAllModals() {
        this.hideTransactionModal();
        this.hideBudgetModal();
        this.hideGoalModal();
    }

    async deleteTransaction(id) {
        if (!confirm('Are you sure you want to delete this transaction?')) return;
        try {
            const response = await fetch(`${this.apiBase}/transactions.php`, {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${this.token}` },
                body: JSON.stringify({ action: 'delete', id })
            });
            const result = await response.json();
            if (result.success) {
                this.showNotification('Transaction deleted successfully!', 'success');
                await this.loadUserData();
                this.renderAllTransactions();
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch {
            this.showNotification('Failed to delete transaction. Please try again.', 'error');
        }
    }

    editTransaction(id) {
        const t = this.transactions.find(x => x.id == id);
        if (!t) return;
        document.getElementById('transactionId').value = t.id;
        document.getElementById('transactionType').value = t.type;
        document.getElementById('transactionAmount').value = t.amount;
        document.getElementById('transactionCategory').value = t.category_id || '';
        document.getElementById('transactionDate').value = t.date;
        document.getElementById('transactionDescription').value = t.description || '';
        document.getElementById('transactionModalTitle').textContent = 'Edit Transaction';
        this.showTransactionModal(t.type);
    }

    toggleAuthForms() {
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        if (loginForm.classList.contains('hidden')) {
            loginForm.classList.remove('hidden');
            registerForm.classList.add('hidden');
        } else {
            loginForm.classList.add('hidden');
            registerForm.classList.remove('hidden');
        }
    }

    showAuth() {
        document.getElementById('authContainer').classList.remove('hidden');
        document.getElementById('appContainer').classList.add('hidden');
    }

    showApp() {
        document.getElementById('authContainer').classList.add('hidden');
        document.getElementById('appContainer').classList.remove('hidden');
        this.showView('dashboard');
    }

    logout() {
        this.user = null;
        this.token = null;
        this.categories = [];
        this.transactions = [];
        this.budgets = [];
        this.goals = [];
        this.bills = [];
        localStorage.removeItem('expense_tracker_token');
        localStorage.removeItem('expense_tracker_user');
        this.showAuth();
        this.showNotification('Logged out successfully!', 'success');
    }

    showNotification(message, type = 'success') {
        const toast = document.getElementById('notificationToast');
        const messageEl = document.getElementById('notificationMessage');
        messageEl.textContent = message;
        toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50 ${
            type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
        }`;
        setTimeout(() => { toast.classList.remove('translate-x-full'); }, 100);
        setTimeout(() => { toast.classList.add('translate-x-full'); }, 3000);
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    formatMonth(monthString) {
        const date = new Date(monthString + '-01');
        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    }
}

let app;
document.addEventListener('DOMContentLoaded', () => { app = new ExpenseTracker(); });
