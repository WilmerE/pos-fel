// Global State
function parseUser() {
    try {
        const userData = localStorage.getItem('user');
        if (!userData || userData === 'null' || userData === 'undefined') {
            return null;
        }
        return JSON.parse(userData);
    } catch (e) {
        localStorage.removeItem('user');
        return null;
    }
}

const state = {
    token: localStorage.getItem('auth_token') || null,
    user: parseUser(),
    currentSale: null,
    currentCashBox: null,
    searchResults: [], // Store search results temporarily
    apiUrl: window.location.origin + '/api'
};

// API Helper
async function apiRequest(endpoint, options) {
    if (!options) options = {};
    
    showLoading();
    try {
        var headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        
        if (state.token) {
            headers['Authorization'] = 'Bearer ' + state.token;
        }
        
        if (options.headers) {
            for (var key in options.headers) {
                headers[key] = options.headers[key];
            }
        }
        
        const config = {
            method: options.method || 'GET',
            headers: headers
        };
        
        if (options.body) {
            config.body = options.body;
        }

        const response = await fetch(state.apiUrl + endpoint, config);
        const data = await response.json();

        if (!response.ok) {
            // Log only server errors (500+), not business validation errors
            if (response.status >= 500) {
                console.error('Error del servidor:', {
                    status: response.status,
                    message: data.message
                });
            }
            
            // Show validation errors if available
            if (data.errors) {
                const errorMessages = Object.values(data.errors).flat().join(', ');
                throw new Error(errorMessages || data.message || 'Error en la solicitud');
            }
            
            const error = new Error(data.message || 'Error en la solicitud');
            // Mark stock errors for special handling
            if (data.message && (data.message.includes('Stock insuficiente') || data.message.includes('Sin existencias'))) {
                error.isStockError = true;
            }
            // Mark info errors (like cash box not open)
            if (data.message && (data.message.includes('💰') || data.message.includes('debes') || data.message.includes('primero'))) {
                error.isInfoError = true;
            }
            throw error;
        }

        return data;
    } catch (error) {
        // Use appropriate toast type based on error
        let toastType = 'error';
        if (error.isStockError) toastType = 'warning';
        if (error.isInfoError) toastType = 'info';
        
        showToast(error.message, toastType);
        throw error;
    } finally {
        hideLoading();
    }
}

// UI Helpers
function showLoading() {
    document.getElementById('loading-overlay').classList.add('show');
}

function hideLoading() {
    document.getElementById('loading-overlay').classList.remove('show');
}

function showToast(message, type) {
    if (!type) type = 'info';
    
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<span>' + message + '</span>';
    container.appendChild(toast);

    setTimeout(function() {
        toast.remove();
    }, 4000);
}

// Alias for showToast to match import code
function showNotification(message, type) {
    if (!type) type = 'info';
    showToast(message, type);
}

// Alias for showToast
function showAlert(message, type) {
    if (!type) type = 'info';
    showToast(message, type);
}

function switchScreen(screenId) {
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    const targetScreen = document.getElementById(screenId);
    if (targetScreen) {
        targetScreen.classList.add('active');
    }
}

function switchModule(moduleName) {
    // Update nav
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.module === moduleName) {
            item.classList.add('active');
        }
    });

    // Update modules
    document.querySelectorAll('.module').forEach(module => {
        module.classList.remove('active');
    });
    document.getElementById(`${moduleName}-module`).classList.add('active');

    // Load module data
    loadModuleData(moduleName);
}

// Authentication
function quickLogin(email) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = 'password';
}

async function login(email, password) {
    try {
        const response = await apiRequest('/login', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });
        
        state.token = response.data.token;
        state.user = response.data.user;
        localStorage.setItem('auth_token', response.data.token);
        localStorage.setItem('user', JSON.stringify(response.data.user));
        
        showToast('Sesión iniciada correctamente', 'success');
        initApp();
    } catch (error) {
        document.getElementById('login-error').textContent = error.message;
        document.getElementById('login-error').classList.add('show');
    }
}

function logout() {
    if (confirm('¿Cerrar sesión?')) {
        apiRequest('/logout', { method: 'POST' }).catch(() => {});
        state.token = null;
        state.user = null;
        state.currentSale = null;
        state.currentCashBox = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        switchScreen('login-screen');
    }
}

function initApp() {
    switchScreen('app-screen');
    document.getElementById('user-name').textContent = state.user.name;
    document.getElementById('user-role').textContent = state.user.roles?.[0] || 'Usuario';
    
    // Load initial data that requires authentication
    loadProductCategories();
    loadSuppliers();
    
    // Setup search clear buttons
    setupSearchClearButtons();
    
    loadModuleData('dashboard');
}

// Module Data Loading
async function loadModuleData(moduleName) {
    switch (moduleName) {
        case 'dashboard':
            loadDashboard();
            break;
        case 'cash-box':
            initCashBoxModule();
            break;
        case 'sales':
            initSalesModule();
            break;
        case 'stock':
            initStockModule();
            break;
        case 'products':
            loadProductCatalog();
            break;
        case 'fiscal':
            loadRecentFiscalDocuments();
            break;
    }
}

// Dashboard Module
async function loadDashboard() {
    try {
        // Load dashboard statistics
        const response = await apiRequest('/dashboard/stats');
        const data = response.data;
        
        // Update stat cards
        document.getElementById('cash-status').textContent = data.current_cash_box.is_open ? 'Abierta' : 'Cerrada';
        document.getElementById('sales-today').textContent = data.today.sales_count;
        document.getElementById('sales-today-total').textContent = `Q ${formatNumber(data.today.sales_total)}`;
        document.getElementById('sales-month').textContent = data.month.sales_count;
        document.getElementById('sales-month-total').textContent = `Q ${formatNumber(data.month.sales_total)}`;
        document.getElementById('low-stock-count').textContent = data.low_stock_count;

        // Render top products
        renderTopProducts(data.top_products);
        
        // Render sales by day
        renderSalesByDay(data.sales_by_day);
        
        // Render cash box history
        renderCashBoxHistory(data.cash_box_history);
        
        // Load expiration notifications
        loadExpirationNotifications();
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showAlert('Error al cargar el dashboard', 'error');
    }
}

// Render top products
function renderTopProducts(products) {
    const container = document.getElementById('top-products-list');
    
    if (!products || products.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay datos disponibles</p>';
        return;
    }
    
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style="text-align: right;">Unidades Vendidas</th>
                </tr>
            </thead>
            <tbody>
                ${products.map(product => `
                    <tr>
                        <td>${product.name}</td>
                        <td style="text-align: right;">${product.total_sold}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    container.innerHTML = html;
}

// Render sales by day
function renderSalesByDay(sales) {
    const container = document.getElementById('sales-by-day-list');
    
    if (!sales || sales.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay datos disponibles</p>';
        return;
    }
    
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th style="text-align: right;">Ventas</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                ${sales.map(day => `
                    <tr>
                        <td>${formatDate(day.date)}</td>
                        <td style="text-align: right;">${day.count}</td>
                        <td style="text-align: right;">Q ${formatNumber(day.total)}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    container.innerHTML = html;
}

// Render cash box history
function renderCashBoxHistory(history) {
    const container = document.getElementById('cash-box-history-table');
    
    if (!history || history.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay historial disponible</p>';
        return;
    }
    
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha Apertura</th>
                    <th>Fecha Cierre</th>
                    <th style="text-align: right;">Monto Inicial</th>
                    <th style="text-align: right;">Ventas</th>
                    <th style="text-align: right;">Monto Final</th>
                    <th style="text-align: right;">Diferencia</th>
                    <th>Abierta por</th>
                    <th>Cerrada por</th>
                </tr>
            </thead>
            <tbody>
                ${history.map(box => `
                    <tr>
                        <td>${formatDateTime(box.opened_at)}</td>
                        <td>${formatDateTime(box.closed_at)}</td>
                        <td style="text-align: right;">Q ${formatNumber(box.opening_amount)}</td>
                        <td style="text-align: right;">Q ${formatNumber(box.total_sales)}</td>
                        <td style="text-align: right;">Q ${formatNumber(box.closing_amount)}</td>
                        <td style="text-align: right; color: ${box.difference >= 0 ? 'green' : 'red'};">Q ${formatNumber(box.difference)}</td>
                        <td>${box.opened_by}</td>
                        <td>${box.closed_by}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    container.innerHTML = html;
}

// Expiration Notifications
let notificationsState = {
    notifications: [],
    panelOpen: false
};

async function loadExpirationNotifications() {
    try {
        const response = await apiRequest('/products/expiration-notifications');
        notificationsState.notifications = response.data;
        const summary = response.summary;
        
        // Update badge in header
        updateNotificationsBadge(summary.total);
        
        // Update dashboard section
        updateDashboardNotifications(notificationsState.notifications, summary);
        
        // Update panel if open
        if (notificationsState.panelOpen) {
            updateNotificationsPanel(notificationsState.notifications, summary);
        }
    } catch (error) {
        console.error('Error loading expiration notifications:', error);
    }
}

function updateNotificationsBadge(count) {
    const badge = document.getElementById('notifications-badge');
    if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'block';
    } else {
        badge.style.display = 'none';
    }
}

function updateDashboardNotifications(notifications, summary) {
    const section = document.getElementById('expiration-notifications-section');
    
    if (!notifications || notifications.length === 0) {
        section.innerHTML = '';
        return;
    }
    
    const expired = notifications.filter(n => n.is_expired);
    const expiringSoon = notifications.filter(n => !n.is_expired);
    
    let html = '';
    
    // Expired products alert
    if (expired.length > 0) {
        html += `
            <div class="card expiration-alert-card has-expired">
                <div class="expiration-alert-header">
                    <div>
                        <h3 class="expiration-alert-title">⚠️ Productos Vencidos</h3>
                        <p style="font-size: 13px; color: #ef4444; margin-top: 4px;">Requieren atención inmediata</p>
                    </div>
                    <div class="expiration-alert-count">${expired.length}</div>
                </div>
                <div style="max-height: 200px; overflow-y: auto;">
                    ${expired.slice(0, 5).map(n => `
                        <div style="padding: 8px; background: white; border-radius: 6px; margin-bottom: 6px; border-left: 3px solid #ef4444;">
                            <div style="font-weight: 600; font-size: 14px; color: #1e293b;">${n.product_name}</div>
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                📦 Lote: ${n.batch_number} | 📍 ${n.location || 'Sin ubicación'} | Cantidad: ${n.quantity_available} uds
                            </div>
                            <div style="font-size: 12px; color: #ef4444; margin-top: 4px; font-weight: 500;">
                                ${n.message} (${n.expiration_date_formatted})
                            </div>
                        </div>
                    `).join('')}
                </div>
                ${expired.length > 5 ? `<p style="text-align: center; margin-top: 8px; color: #64748b; font-size: 12px;">Y ${expired.length - 5} más...</p>` : ''}
                <button class="btn btn-sm btn-danger" onclick="toggleNotificationsPanel()" style="width: 100%; margin-top: 12px;">
                    Ver Todos los Vencidos
                </button>
            </div>
        `;
    }
    
    // Expiring soon alert
    if (expiringSoon.length > 0) {
        html += `
            <div class="card expiration-alert-card expiring-soon">
                <div class="expiration-alert-header">
                    <div>
                        <h3 class="expiration-alert-title">⏰ Productos Próximos a Vencer</h3>
                        <p style="font-size: 13px; color: #f59e0b; margin-top: 4px;">Vencen en los próximos 30 días</p>
                    </div>
                    <div class="expiration-alert-count">${expiringSoon.length}</div>
                </div>
                <div style="max-height: 200px; overflow-y: auto;">
                    ${expiringSoon.slice(0, 5).map(n => `
                        <div style="padding: 8px; background: white; border-radius: 6px; margin-bottom: 6px; border-left: 3px solid ${n.urgency === 'critical' ? '#f97316' : '#f59e0b'};">
                            <div style="font-weight: 600; font-size: 14px; color: #1e293b;">${n.product_name}</div>
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                📦 Lote: ${n.batch_number} | 📍 ${n.location || 'Sin ubicación'} | Cantidad: ${n.quantity_available} uds
                            </div>
                            <div style="font-size: 12px; color: ${n.urgency === 'critical' ? '#f97316' : '#f59e0b'}; margin-top: 4px; font-weight: 500;">
                                ${n.message} (${n.expiration_date_formatted})
                            </div>
                        </div>
                    `).join('')}
                </div>
                ${expiringSoon.length > 5 ? `<p style="text-align: center; margin-top: 8px; color: #64748b; font-size: 12px;">Y ${expiringSoon.length - 5} más...</p>` : ''}
                <button class="btn btn-sm btn-warning" onclick="toggleNotificationsPanel()" style="width: 100%; margin-top: 12px; background: #f59e0b;">
                    Ver Todos
                </button>
            </div>
        `;
    }
    
    section.innerHTML = html;
}

function toggleNotificationsPanel() {
    const panel = document.getElementById('notifications-panel');
    notificationsState.panelOpen = !notificationsState.panelOpen;
    
    if (notificationsState.panelOpen) {
        panel.style.display = 'flex';
        updateNotificationsPanel(notificationsState.notifications);
    } else {
        panel.style.display = 'none';
    }
}

function updateNotificationsPanel(notifications) {
    const content = document.getElementById('notifications-content');
    
    if (!notifications || notifications.length === 0) {
        content.innerHTML = '<p class="text-muted" style="text-align: center; padding: 40px 20px;">No hay notificaciones de vencimientos</p>';
        return;
    }
    
    const html = notifications.map(n => {
        const urgencyClass = n.is_expired ? 'expired' : n.urgency;
        const icon = n.is_expired ? '🚫' : (n.urgency === 'critical' ? '⚠️' : '⏰');
        
        return `
            <div class="notification-item ${urgencyClass}" onclick="showProductDetails(${n.product_id})">
                <div class="notification-item-header">
                    <div class="notification-item-title">${icon} ${n.product_name}</div>
                    <span class="notification-item-badge ${urgencyClass}">
                        ${n.is_expired ? 'VENCIDO' : (n.urgency === 'critical' ? 'URGENTE' : 'PRÓXIMO')}
                    </span>
                </div>
                <div class="notification-item-details">
                    <div><strong>Lote:</strong> ${n.batch_number}</div>
                    <div><strong>Cantidad:</strong> ${n.quantity_available} unidades</div>
                    <div><strong>Vencimiento:</strong> ${n.expiration_date_formatted} - ${n.message}</div>
                </div>
                <div class="notification-item-location">📍 ${n.location || 'Sin ubicación'}</div>
            </div>
        `;
    }).join('');
    
    content.innerHTML = html;
}

// Cash Box Module
function initCashBoxModule() {
    loadCashBoxSummary();
    
    // Initialize history if not already done
    if (!document.getElementById('cashbox-year-filter').value) {
        initializeCashBoxHistory();
    }
}



async function loadCashBoxSummary() {
    try {
        const response = await apiRequest('/cash-box/summary');
        const cashBox = response.data;
        state.currentCashBox = cashBox;

        const content = document.getElementById('cash-box-content');
        
        if (cashBox.status === 'open') {
            const openingAmount = parseFloat(cashBox.opening_amount || 0);
            const totalIncome = parseFloat(cashBox.totals?.income || 0);
            const totalExpenses = parseFloat(cashBox.totals?.expenses || 0);
            const currentCash = openingAmount + totalIncome - totalExpenses;
            
            content.innerHTML = `
                <div class="alert success">
                    <strong>Caja Abierta</strong><br>
                    Operando desde: ${new Date(cashBox.opened_at).toLocaleString()}
                </div>
                <div class="cash-summary">
                    <div class="cash-info-item">
                        <label>Efectivo Inicial</label>
                        <div class="value">Q ${openingAmount.toFixed(2)}</div>
                    </div>
                    <div class="cash-info-item">
                        <label>Total Ingresos</label>
                        <div class="value">Q ${totalIncome.toFixed(2)}</div>
                    </div>
                    <div class="cash-info-item">
                        <label>Total Egresos</label>
                        <div class="value">Q ${totalExpenses.toFixed(2)}</div>
                    </div>
                    <div class="cash-info-item">
                        <label>Efectivo Actual</label>
                        <div class="value">Q ${currentCash.toFixed(2)}</div>
                    </div>
                    <div class="cash-info-item">
                        <label>Cierre Esperado</label>
                        <div class="value">Q ${parseFloat(cashBox.expected_closing || 0).toFixed(2)}</div>
                    </div>
                </div>
            `;
        } else {
            content.innerHTML = `
                <div class="alert warning">
                    <strong>Caja Cerrada</strong><br>
                    Debe abrir la caja para comenzar a operar.
                </div>
            `;
        }

        loadCashMovements();
    } catch (error) {
        document.getElementById('cash-box-content').innerHTML = `
            <div class="alert error">
                <strong>Error al cargar información de caja</strong><br>
                ${error.message}
            </div>
        `;
    }
}

async function openCashBox() {
    const initialCash = prompt('Ingrese el efectivo inicial:');
    if (initialCash === null) return;

    try {
        await apiRequest('/cash-box/open', {
            method: 'POST',
            body: JSON.stringify({ 
                initial_cash: parseFloat(initialCash)
            })
        });
        showToast('Caja abierta correctamente', 'success');
        loadCashBoxSummary();
        
        // Update sales button state if we're in the sales module
        if (document.getElementById('sales-module').classList.contains('active')) {
            checkCashBoxStatusForSales();
            // Load current cash box sales
            document.getElementById('current-cashbox-sales-section').style.display = 'block';
            loadCurrentCashBoxSales();
        }
    } catch (error) {
        // Error already shown in apiRequest
    }
}

async function closeCashBox() {
    const finalCash = prompt('Ingrese el efectivo final contado:');
    if (finalCash === null) return;

    try {
        const response = await apiRequest('/cash-box/close', {
            method: 'POST',
            body: JSON.stringify({ 
                final_cash: parseFloat(finalCash)
            })
        });
        
        const difference = response.data.difference;
        let message = 'Caja cerrada correctamente.';
        if (difference > 0) {
            message += ` Sobrante: Q ${difference.toFixed(2)}`;
        } else if (difference < 0) {
            message += ` Faltante: Q ${Math.abs(difference).toFixed(2)}`;
        }
        
        showToast(message, difference === 0 ? 'success' : 'info');
        loadCashBoxSummary();
        
        // Reload cash box history if we're in the cash box module
        if (document.getElementById('cashbox-module').classList.contains('active')) {
            loadCashBoxHistory();
        }
        
        // Update sales button state if we're in the sales module
        if (document.getElementById('sales-module').classList.contains('active')) {
            checkCashBoxStatusForSales();
            // Clear current cash box sales
            document.getElementById('current-cashbox-sales-section').style.display = 'none';
            document.getElementById('current-cashbox-sales-list').innerHTML = '<p class="text-muted">No hay ventas registradas en esta caja</p>';
            document.getElementById('current-cashbox-totals').style.display = 'none';
        }
    } catch (error) {
        // Error already shown
    }
}

async function loadCashMovements() {
    try {
        const response = await apiRequest('/cash-box/movements');
        const movements = response.data;

        const container = document.getElementById('cash-movements-list');
        
        if (movements.length === 0) {
            container.innerHTML = '<p class="text-muted">No hay movimientos registrados</p>';
            return;
        }

        container.innerHTML = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Monto</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    ${movements.map(mov => `
                        <tr>
                            <td>${new Date(mov.created_at).toLocaleString()}</td>
                            <td><span class="badge ${mov.type}">${mov.type}</span></td>
                            <td>Q ${parseFloat(mov.amount).toFixed(2)}</td>
                            <td>${mov.description || '-'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } catch (error) {
        console.error('Error loading movements:', error);
    }
}

function initializeCashBoxHistory() {
    // Populate year filter
    const yearSelect = document.getElementById('cashbox-year-filter');
    const currentYear = new Date().getFullYear();
    const startYear = 2024; // Adjust based on your needs
    
    yearSelect.innerHTML = '';
    for (let year = currentYear; year >= startYear; year--) {
        const option = document.createElement('option');
        option.value = year;
        option.textContent = year;
        yearSelect.appendChild(option);
    }
    
    // Add event listener for year change
    yearSelect.addEventListener('change', function() {
        loadCashBoxHistory();
    });
    
    // Add event listeners for month buttons
    const monthButtons = document.querySelectorAll('#cashbox-month-filters .month-btn');
    monthButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active class from all buttons
            monthButtons.forEach(b => b.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
            // Load cash boxes with new filter
            loadCashBoxHistory();
        });
    });
    
    // Load initial data
    loadCashBoxHistory();
}

async function loadCashBoxHistory() {
    try {
        const year = document.getElementById('cashbox-year-filter').value;
        const activeMonthBtn = document.querySelector('#cashbox-month-filters .month-btn.active');
        const month = activeMonthBtn ? activeMonthBtn.getAttribute('data-month') : '';
        
        let url = `/cash-box?year=${year}`;
        if (month) {
            url += `&month=${month}`;
        }
        
        const response = await apiRequest(url);
        const cashBoxes = response.data;
        
        const container = document.getElementById('cashbox-history-list');
        const totalsDiv = document.getElementById('cashbox-history-totals');
        
        if (cashBoxes.length === 0) {
            container.innerHTML = '<p class="text-muted">No hay cajas en este período</p>';
            totalsDiv.style.display = 'none';
            return;
        }
        
        // Calculate totals
        const closedCount = cashBoxes.filter(cb => cb.status === 'closed').length;
        const openCount = cashBoxes.filter(cb => cb.status === 'open').length;
        
        // Display cash boxes table
        container.innerHTML = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha Apertura</th>
                        <th>Fecha Cierre</th>
                        <th style="text-align: right;">Monto Inicial</th>
                        <th style="text-align: right;">Ventas</th>
                        <th style="text-align: right;">Monto Final</th>
                        <th style="text-align: right;">Diferencia</th>
                        <th>Abierta por</th>
                        <th>Cerrada por</th>
                    </tr>
                </thead>
                <tbody>
                    ${cashBoxes.map(cb => {
                        const difference = cb.difference !== null && cb.difference !== undefined 
                            ? parseFloat(cb.difference).toFixed(2) 
                            : '0.00';
                        const diffColor = difference > 0 ? '#10b981' : difference < 0 ? '#dc2626' : '#6b7280';
                        const isClosed = cb.status === 'closed' || cb.closed_at;
                        
                        return `
                            <tr>
                                <td>${formatDateTime(cb.opened_at)}</td>
                                <td>${cb.closed_at ? formatDateTime(cb.closed_at) : '-'}</td>
                                <td style="text-align: right;">Q ${formatNumber(cb.initial_amount || 0)}</td>
                                <td style="text-align: right;">Q ${formatNumber(cb.total_sales || 0)}</td>
                                <td style="text-align: right;">${isClosed ? 'Q ' + formatNumber(cb.final_cash || 0) : '-'}</td>
                                <td style="text-align: right; color: ${diffColor}; font-weight: 600;">
                                    ${isClosed ? 'Q ' + difference : '-'}
                                </td>
                                <td>${cb.opened_by ? cb.opened_by.name : 'N/A'}</td>
                                <td>${cb.closed_by ? cb.closed_by.name : '-'}</td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        `;
        
        // Display totals
        const totalSales = cashBoxes.reduce((sum, cb) => sum + (parseFloat(cb.total_sales) || 0), 0);
        document.getElementById('cashbox-history-count').textContent = cashBoxes.length;
        document.getElementById('cashbox-history-closed').textContent = closedCount;
        document.getElementById('cashbox-history-open').textContent = openCount;
        document.getElementById('cashbox-history-sales').textContent = formatNumber(totalSales);
        totalsDiv.style.display = 'block';
        
    } catch (error) {
        console.error('Error loading cash box history:', error);
        const container = document.getElementById('cashbox-history-list');
        container.innerHTML = '<p class="text-muted">Error al cargar el historial de cajas</p>';
    }
}

// Sales Module
async function initSalesModule() {
    // Show buttons initially
    document.getElementById('sales-header-actions').style.display = 'flex';
    
    // Check cash box status and show relevant sections
    await checkCashBoxStatusForSales();
    showSalesModule();
    
    try {
        // Check if there are pending sales
        const response = await apiRequest('/sales/pending');
        const sales = response.data;
        
        if (sales.length > 0) {
            // Automatically load pending sales if any exist
            showPendingSales(sales);
        }
    } catch (error) {
        console.error('Error checking pending sales:', error);
    }
}

async function checkCashBoxStatusForSales() {
    try {
        const response = await apiRequest('/cash-box/summary');
        const cashBox = response.data;
        
        const newSaleBtn = document.getElementById('new-sale-btn');
        const warning = document.getElementById('cash-box-closed-warning');
        
        if (cashBox.status === 'open') {
            // Cash box is open - enable button and hide warning
            newSaleBtn.disabled = false;
            newSaleBtn.style.opacity = '1';
            newSaleBtn.style.cursor = 'pointer';
            warning.style.display = 'none';
        } else {
            // Cash box is closed - disable button and show warning
            newSaleBtn.disabled = true;
            newSaleBtn.style.opacity = '0.5';
            newSaleBtn.style.cursor = 'not-allowed';
            warning.style.display = 'block';
        }
    } catch (error) {
        console.error('Error checking cash box status:', error);
        // On error, disable button to be safe
        const newSaleBtn = document.getElementById('new-sale-btn');
        newSaleBtn.disabled = true;
        newSaleBtn.style.opacity = '0.5';
    }
}

function showNewSaleForm() {
    document.getElementById('new-sale-section').style.display = 'block';
    document.getElementById('current-sale-section').style.display = 'none';
    document.getElementById('pending-sales-section').style.display = 'none';
    // Hide header actions when showing form
    document.getElementById('sales-header-actions').style.display = 'none';
}

async function loadPendingSales() {
    try {
        const response = await apiRequest('/sales/pending');
        const sales = response.data;
        showPendingSales(sales);
    } catch (error) {
        console.error('Error loading pending sales:', error);
    }
}

function showPendingSales(sales) {
    document.getElementById('new-sale-section').style.display = 'none';
    document.getElementById('current-sale-section').style.display = 'none';
    document.getElementById('pending-sales-section').style.display = 'block';
    // Keep header actions visible when viewing pending sales
    document.getElementById('sales-header-actions').style.display = 'flex';

    const container = document.getElementById('pending-sales-list');
    
    if (sales.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay ventas pendientes</p>';
        return;
    }

    container.innerHTML = `
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Total</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${sales.map(sale => `
                        <tr>
                            <td>#${sale.id}</td>
                            <td>${sale.customer_name || 'Sin cliente'}</td>
                            <td>Q ${parseFloat(sale.total || 0).toFixed(2)}</td>
                            <td>${new Date(sale.created_at).toLocaleString()}</td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="loadSale(${sale.id})">Ver</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
}

async function loadSale(saleId) {
    try {
        const response = await apiRequest(`/sales/${saleId}`);
        state.currentSale = response.data;
        showCurrentSale();
    } catch (error) {
        // Error shown in apiRequest
    }
}

function showCurrentSale() {
    document.getElementById('new-sale-section').style.display = 'none';
    document.getElementById('current-sale-section').style.display = 'block';
    document.getElementById('pending-sales-section').style.display = 'none';
    // Hide header actions when working on a sale
    document.getElementById('sales-header-actions').style.display = 'none';

    const sale = state.currentSale;
    document.getElementById('current-sale-id').textContent = sale.id;
    document.getElementById('sale-customer').textContent = sale.customer_name 
        ? `${sale.customer_name}${sale.customer_nit ? ' (NIT: ' + sale.customer_nit + ')' : ''}`
        : 'Sin cliente';
    document.getElementById('sale-status').textContent = sale.status;
    document.getElementById('sale-status').className = `badge ${sale.status}`;

    updateSaleItems();
    updateSaleTotals();
}

function cancelNewSale() {
    // Reset form
    document.getElementById('new-sale-form').reset();
    // Hide form and show buttons
    document.getElementById('new-sale-section').style.display = 'none';
    document.getElementById('sales-header-actions').style.display = 'flex';
    // Check if there are pending sales to show
    initSalesModule();
}

function backToPendingSales() {
    // Simply go back to pending sales view without affecting the sale
    initSalesModule();
}

function updateSaleItems() {
    const container = document.getElementById('sale-items-list');
    const items = state.currentSale.items || [];

    if (items.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay productos agregados</p>';
        return;
    }

    container.innerHTML = `
        <table class="table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Presentación</th>
                    <th>Cantidad</th>
                    <th>Precio Unit.</th>
                    <th>Subtotal</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                ${items.map(item => `
                    <tr>
                        <td>${item.product || 'Producto #' + item.product_id}</td>
                        <td>${item.presentation || 'Presentación #' + item.presentation_id}</td>
                        <td>${item.quantity}</td>
                        <td>Q ${parseFloat(item.unit_price || 0).toFixed(2)}</td>
                        <td>Q ${parseFloat(item.subtotal || 0).toFixed(2)}</td>
                        <td>
                            <button class="btn btn-sm btn-danger" onclick="removeItem(${item.id})">Eliminar</button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function updateSaleTotals() {
    const sale = state.currentSale;
    document.getElementById('sale-subtotal').textContent = parseFloat(sale.subtotal || 0).toFixed(2);
    document.getElementById('sale-tax').textContent = parseFloat(sale.tax || 0).toFixed(2);
    document.getElementById('sale-total').textContent = parseFloat(sale.total || 0).toFixed(2);
}

async function confirmCurrentSale() {
    if (!confirm('¿Confirmar esta venta?')) return;

    try {
        const response = await apiRequest(`/sales/${state.currentSale.id}/confirm`, {
            method: 'POST'
        });
        showToast('✅ Venta confirmada correctamente', 'success');
        
        // Clear current sale and reinitialize module
        setTimeout(() => {
            state.currentSale = null;
            initSalesModule();
        }, 1500);
    } catch (error) {
        // Error shown
    }
}

async function cancelCurrentSale() {
    if (!confirm('¿Cancelar esta venta?')) return;

    try {
        await apiRequest(`/sales/${state.currentSale.id}/cancel`, {
            method: 'POST'
        });
        showToast('Venta cancelada', 'info');
        state.currentSale = null;
        initSalesModule();
    } catch (error) {
        // Error shown
    }
}

async function removeItem(itemId) {
    if (!confirm('¿Eliminar este producto?')) return;

    try {
        const response = await apiRequest(`/sales/${state.currentSale.id}/items/${itemId}`, {
            method: 'DELETE'
        });
        state.currentSale = response.data;
        updateSaleItems();
        updateSaleTotals();
        showToast('Producto eliminado', 'info');
    } catch (error) {
        // Error shown
    }
}

function selectProduct(index) {
    // Get product from state
    const product = state.searchResults[index];
    if (!product) return;
    
    // Hide search results
    document.getElementById('search-results').style.display = 'none';
    
    // Clear search input
    document.getElementById('product-search').value = '';
    
    // Show product info
    document.getElementById('product-name').textContent = product.name;
    document.getElementById('product-description').textContent = product.description || 'Sin descripción';
    document.getElementById('selected-product-id').value = product.id;
    
    // Populate presentations dropdown with IVA included prices
    const select = document.getElementById('presentation-select');
    select.innerHTML = '<option value="">Seleccione presentación</option>' + 
        product.presentations.map(p => 
            `<option value="${p.id}" data-price="${p.price_with_iva}">${p.name} - Q ${parseFloat(p.price_with_iva).toFixed(2)} (IVA incluido)</option>`
        ).join('');
    
    // Show product result section
    document.getElementById('product-result').style.display = 'block';
    document.getElementById('unit-price-display').value = '';
    
    // Reset quantity to 1
    document.querySelector('#add-item-form input[name="quantity"]').value = 1;
}

// Stock Module
function initStockModule() {
    // Show check stock section by default
    document.getElementById('check-stock-section').style.display = 'block';
    document.getElementById('add-stock-section').style.display = 'none';
    document.getElementById('stock-header-actions').style.display = 'flex';
}

function showAddStockForm() {
    document.getElementById('add-stock-section').style.display = 'block';
    document.getElementById('check-stock-section').style.display = 'none';
    // Hide header actions
    document.getElementById('stock-header-actions').style.display = 'none';
}

function cancelAddStock() {
    // Reset form
    document.getElementById('add-stock-form').reset();
    
    // Clear barcode search
    const stockBarcodeWrapper = document.getElementById('stock-barcode-wrapper');
    if (stockBarcodeWrapper) {
        stockBarcodeWrapper.classList.remove('has-value');
    }
    
    // Hide product info
    hideStockProductInfo();
    
    // Show check stock section and hide add form
    document.getElementById('add-stock-section').style.display = 'none';
    document.getElementById('check-stock-section').style.display = 'block';
    document.getElementById('stock-header-actions').style.display = 'flex';
}

// Fiscal Module
async function loadRecentFiscalDocuments() {
    // This would need a proper endpoint - for now just show message
    document.getElementById('fiscal-documents-list').innerHTML = `
        <p class="text-muted">Los documentos fiscales se generan automáticamente al confirmar ventas</p>
    `;
}

// Event Listeners
document.addEventListener('DOMContentLoaded', () => {
    // Check if already logged in
    if (state.token && state.user) {
        initApp();
    }

    // Close modal when clicking outside
    document.getElementById('product-details-modal')?.addEventListener('click', (e) => {
        if (e.target.id === 'product-details-modal') {
            closeProductDetails();
        }
    });

    // Close notifications panel when clicking outside
    document.addEventListener('click', (e) => {
        const panel = document.getElementById('notifications-panel');
        const bell = document.getElementById('notifications-bell');
        if (notificationsState.panelOpen && 
            !panel.contains(e.target) && 
            !bell.contains(e.target)) {
            toggleNotificationsPanel();
        }
    });

    // Login form
    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = e.target.email.value;
        const password = e.target.password.value;
        await login(email, password);
    });

    // Navigation
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', () => {
            switchModule(item.dataset.module);
        });
    });

    // Cash movement form
    document.getElementById('cash-movement-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);

        try {
            const endpoint = data.type === 'income' ? '/cash-box/income' : '/cash-box/expense';
            await apiRequest(endpoint, {
                method: 'POST',
                body: JSON.stringify({
                    amount: parseFloat(data.amount),
                    description: data.description
                })
            });
            showToast('Movimiento registrado', 'success');
            e.target.reset();
            loadCashBoxSummary();
        } catch (error) {
            // Error shown
        }
    });

    // New sale form
    document.getElementById('new-sale-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);

        try {
            const response = await apiRequest('/sales', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            state.currentSale = response.data;
            showToast('Venta creada', 'success');
            e.target.reset();
            showCurrentSale();
        } catch (error) {
            // Error shown
        }
    });

    // Product search with real-time autocomplete
    let searchTimeout;
    const searchInput = document.getElementById('product-search');
    const searchResults = document.getElementById('search-results');

    searchInput.addEventListener('input', (e) => {
        const search = e.target.value.trim();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Hide results if search is empty
        if (search.length === 0) {
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
            return;
        }
        
        // Debounce search - wait 300ms after user stops typing
        searchTimeout = setTimeout(async () => {
            try {
                const response = await apiRequest(`/products/search?search=${encodeURIComponent(search)}`);
                const products = response.data;
                
                // Store results in state
                state.searchResults = products;
                
                if (products.length === 0) {
                    searchResults.innerHTML = '<div class="search-no-results">No se encontraron productos</div>';
                    searchResults.style.display = 'block';
                    return;
                }
                
                // Display results using index
                searchResults.innerHTML = products.map((product, index) => {
                    let warningBadges = '';
                    
                    // Check for warnings
                    if (product.has_expired_batches) {
                        warningBadges += '<span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 8px;">🚫 VENCIDO</span>';
                    }
                    if (product.has_expiring_soon_batches) {
                        warningBadges += '<span style="background: #f97316; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 8px;">⚠️ Por vencer</span>';
                    }
                    if (product.stock_warning && product.total_stock > 0) {
                        warningBadges += '<span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 8px;">📦 Stock bajo</span>';
                    }
                    if (product.total_stock === 0) {
                        warningBadges += '<span style="background: #94a3b8; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 8px;">❌ Sin stock</span>';
                    }
                    
                    return `
                        <div class="search-result-item" data-index="${index}">
                            <div class="product-name">
                                ${product.name}
                                ${warningBadges}
                            </div>
                            <div class="product-info">
                                <span class="product-barcode">${product.barcode}</span>
                                ${product.description || ''}
                                ${product.total_stock !== undefined ? `<span style="margin-left: 12px; color: #64748b;">Stock: ${product.total_stock}</span>` : ''}
                            </div>
                        </div>
                    `;
                }).join('');
                
                // Add click event listeners to result items
                searchResults.querySelectorAll('.search-result-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const index = parseInt(item.getAttribute('data-index'));
                        selectProduct(index);
                    });
                });
                
                searchResults.style.display = 'block';
            } catch (error) {
                searchResults.style.display = 'none';
            }
        }, 300);
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#product-search') && !e.target.closest('#search-results')) {
            searchResults.style.display = 'none';
        }
    });

    // Update price when presentation changes
    document.getElementById('presentation-select').addEventListener('change', (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex];
        const price = selectedOption.getAttribute('data-price');
        if (price) {
            document.getElementById('unit-price-display').value = `Q ${parseFloat(price).toFixed(2)}`;
        } else {
            document.getElementById('unit-price-display').value = '';
        }
    });

    // Add item form
    document.getElementById('add-item-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Validate that we have a current sale
        if (!state.currentSale || !state.currentSale.id) {
            showToast('No hay una venta activa. Por favor crea o carga una venta primero.', 'error');
            return;
        }
        
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        const payload = {
            product_id: parseInt(data.product_id),
            presentation_id: parseInt(data.presentation_id),
            quantity: parseInt(data.quantity)
        };

        try {
            await apiRequest(`/sales/${state.currentSale.id}/items`, {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            
            // Reload sale to get updated data
            await loadSale(state.currentSale.id);
            
            showToast('Producto agregado', 'success');
            
            // Reset forms and hide product section
            document.getElementById('product-search').value = '';
            document.getElementById('add-item-form').reset();
            document.getElementById('product-result').style.display = 'none';
        } catch (error) {
            // Error shown
        }
    });

    // Add stock form
    document.getElementById('add-stock-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);

        // Validate presentation_id
        if (!data.presentation_id) {
            showToast('Debe seleccionar una presentación', 'error');
            return;
        }

        try {
            await apiRequest('/stock/add', {
                method: 'POST',
                body: JSON.stringify({
                    product_id: parseInt(data.product_id),
                    presentation_id: parseInt(data.presentation_id),
                    quantity: parseInt(data.quantity),
                    expiration_date: data.expiration_date || null,
                    location: data.location || null,
                    batch_number: data.batch_number || null
                })
            });
            showToast('✅ Stock agregado correctamente', 'success');
            e.target.reset();
            // Clear presentation select
            document.getElementById('stock-presentation').innerHTML = '<option value="">Seleccione presentación</option>';
            // Hide form and show check stock section
            cancelAddStock();
        } catch (error) {
            // Error shown by apiRequest
        }
    });

    // Stock barcode search
    const stockBarcodeInput = document.getElementById('stock-barcode');
    const stockBarcodeWrapper = document.getElementById('stock-barcode-wrapper');
    let stockBarcodeTimeout;
    
    if (stockBarcodeInput && stockBarcodeWrapper) {
        stockBarcodeInput.addEventListener('input', function(e) {
            const barcode = e.target.value.trim();
            
            // Update clear button visibility
            if (barcode.length > 0) {
                stockBarcodeWrapper.classList.add('has-value');
            } else {
                stockBarcodeWrapper.classList.remove('has-value');
                hideStockProductInfo();
                return;
            }
            
            // Search for product after 500ms
            clearTimeout(stockBarcodeTimeout);
            if (barcode.length >= 6) {
                stockBarcodeTimeout = setTimeout(async function() {
                    try {
                        const response = await apiRequest('/products/search?search=' + encodeURIComponent(barcode));
                        
                        if (response.data && response.data.length > 0) {
                            const product = response.data[0];
                            
                            // Check exact match
                            if (product.barcode === barcode) {
                                loadStockProductInfo(product);
                            }
                        } else {
                            hideStockProductInfo();
                        }
                    } catch (error) {
                        hideStockProductInfo();
                    }
                }, 500);
            }
        });
    }
    
    // Functions moved to global scope: loadStockProductInfo, hideStockProductInfo

    // Load presentations when product is selected in stock form (Legacy support - now handled by barcode search)
    document.getElementById('stock-product-id').addEventListener('change', async (e) => {
        const productId = e.target.value;
        const presentationSelect = document.getElementById('stock-presentation');
        
        if (!productId) {
            presentationSelect.innerHTML = '<option value="">Seleccione presentación</option>';
            return;
        }

        try {
            const response = await apiRequest(`/products/${productId}`);
            const product = response.data;
            
            presentationSelect.innerHTML = '<option value="">Seleccione presentación</option>';
            
            if (product.presentations && product.presentations.length > 0) {
                product.presentations.forEach(p => {
                    const option = document.createElement('option');
                    option.value = p.id;
                    option.textContent = `${p.name} (${p.factor} unidades)`;
                    presentationSelect.appendChild(option);
                });
            } else {
                presentationSelect.innerHTML = '<option value="">Sin presentaciones disponibles</option>';
            }
        } catch (error) {
            presentationSelect.innerHTML = '<option value="">Error al cargar presentaciones</option>';
        }
    });

    // Check stock - automatic search on input
    let checkStockTimeout = null;
    const checkStockBarcodeInput = document.getElementById('check-stock-barcode');
    
    if (checkStockBarcodeInput) {
        checkStockBarcodeInput.addEventListener('input', function() {
            const barcode = this.value.trim();
            
            // Clear previous timeout
            if (checkStockTimeout) {
                clearTimeout(checkStockTimeout);
            }
            
            // Clear results if empty
            if (barcode.length === 0) {
                document.getElementById('stock-result').innerHTML = '';
                document.getElementById('stock-batches-list').innerHTML = '';
                document.getElementById('check-stock-product-id').value = '';
                return;
            }
            
            // Search after 500ms
            if (barcode.length >= 4) {
                checkStockTimeout = setTimeout(async () => {
                    try {
                        // First search for product by barcode
                        const searchResponse = await apiRequest('/products/search?search=' + encodeURIComponent(barcode));
                        
                        if (!searchResponse.data || searchResponse.data.length === 0) {
                            document.getElementById('stock-result').innerHTML = `
                                <div class="alert error">
                                    <strong>❌ Producto no encontrado</strong><br>
                                    No existe un producto con el código de barras: ${barcode}
                                </div>
                            `;
                            document.getElementById('stock-batches-list').innerHTML = '';
                            return;
                        }
                        
                        const product = searchResponse.data[0];
                        
                        // Check exact match
                        if (product.barcode !== barcode) {
                            document.getElementById('stock-result').innerHTML = `
                                <div class="alert error">
                                    <strong>❌ Código de barras no coincide exactamente</strong>
                                </div>
                            `;
                            document.getElementById('stock-batches-list').innerHTML = '';
                            return;
                        }
                        
                        const productId = product.id;
                        
                        // Set hidden product_id field
                        document.getElementById('check-stock-product-id').value = productId;
                        
                        // Consultar disponibilidad
                        const response = await apiRequest(`/stock/check/${productId}`);
                        const stock = response.data;
                        
                        document.getElementById('stock-result').innerHTML = `
                            <div class="alert success">
                                <strong>📦 Stock Disponible: ${stock.available_quantity} unidades</strong><br>
                                <div style="margin-top: 8px; color: #475569;">
                                    <strong>Producto:</strong> ${product.name}<br>
                                    <strong>Código:</strong> ${product.barcode}
                                </div>
                            </div>
                        `;
                        
                        // Cargar lotes automáticamente
                        try {
                            const batchesResponse = await apiRequest(`/stock/batches/${productId}`);
                            const batches = batchesResponse.data;
                            const container = document.getElementById('stock-batches-list');
                            
                            if (batches.length === 0) {
                                container.innerHTML = '<p class="text-muted">No hay lotes disponibles para este producto</p>';
                                return;
                            }

                            container.innerHTML = `
                                <h4 style="margin-top: 20px;">Lotes de Inventario</h4>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Lote</th>
                                            <th>Presentación</th>
                                            <th>Cantidad Disponible</th>
                                            <th>Fecha Vencimiento</th>
                                            <th>Ubicación</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${batches.map(batch => `
                                            <tr>
                                                <td>${batch.batch_number || 'N/A'}</td>
                                                <td>${batch.presentation ? batch.presentation.name : 'Unidad'}</td>
                                                <td><strong>${batch.quantity_available}</strong> / ${batch.quantity_initial}</td>
                                                <td>${batch.expiration_date ? new Date(batch.expiration_date).toLocaleDateString('es-GT') : 'N/A'}</td>
                                                <td>${batch.location || 'Sin ubicación'}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            `;
                        } catch (error) {
                            document.getElementById('stock-batches-list').innerHTML = `
                                <p class="text-muted">Error al cargar lotes del producto</p>
                            `;
                        }
                    } catch (error) {
                        document.getElementById('stock-result').innerHTML = `
                            <div class="alert error">
                                <strong>❌ Error al consultar stock</strong><br>
                                ${error.message}
                            </div>
                        `;
                        document.getElementById('stock-batches-list').innerHTML = '';
                    }
                }, 500);
            }
        });
    }

    // Annul sale form
    document.getElementById('annul-sale-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);

        if (!confirm('¿Está seguro de anular esta venta?')) return;

        try {
            await apiRequest('/fiscal/annul', {
                method: 'POST',
                body: JSON.stringify({
                    sale_id: parseInt(data.sale_id),
                    reason: data.reason
                })
            });
            showToast('Solicitud de anulación enviada', 'success');
            e.target.reset();
        } catch (error) {
            // Error shown
        }
    });

    // Product form
    // Auto-complete product data when barcode is entered
    const barcodeInput = document.getElementById('product-barcode');
    if (barcodeInput) {
        let barcodeTimeout;
        let existingProductId = null;
        
        barcodeInput.addEventListener('input', function(e) {
            clearTimeout(barcodeTimeout);
            const barcode = e.target.value.trim();
            
            // Reset form state if barcode is cleared or too short
            if (barcode.length < 6) {
                existingProductId = null;
                enableProductForm();
                return;
            }
            
            // Only search if barcode has at least 6 characters and we're not editing
            if (barcode.length >= 6 && !currentEditingProductId) {
                barcodeTimeout = setTimeout(async function() {
                    try {
                        // Search for product by barcode
                        const response = await apiRequest('/products/search?search=' + encodeURIComponent(barcode));
                        
                        if (response.data && response.data.length > 0) {
                            const product = response.data[0];
                            
                            // Check if barcode matches exactly
                            if (product.barcode === barcode) {
                                existingProductId = product.id;
                                
                                // Auto-fill form with existing product data
                                document.querySelector('#product-form input[name="name"]').value = product.name || '';
                                document.querySelector('#product-form textarea[name="description"]').value = product.description || '';
                                document.querySelector('#product-form select[name="category_id"]').value = product.category_id || '';
                                document.getElementById('product-brand').value = product.brand || '';
                                document.getElementById('product-location').value = product.location || '';
                                document.getElementById('product-supplier').value = product.supplier_id || '';
                                
                                // Disable form to prevent duplicate creation
                                disableProductForm(product.id);
                                
                                // Show warning notification
                                showNotification('⚠️ PRODUCTO YA REGISTRADO: Este código de barras ya existe en el sistema (ID: ' + product.id + '). No se puede crear un producto duplicado. Para modificarlo, use el botón "Editar Producto".', 'error');
                            }
                        } else {
                            // Product not found, enable form
                            existingProductId = null;
                            enableProductForm();
                        }
                    } catch (error) {
                        // Product doesn't exist, enable form
                        existingProductId = null;
                        enableProductForm();
                    }
                }, 500);
            }
        });
    }

    document.getElementById('product-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);

        // Remove empty fields
        if (!data.category_id) delete data.category_id;
        if (!data.brand) delete data.brand;
        if (!data.location) delete data.location;
        if (!data.supplier_id) delete data.supplier_id;

        try {
            if (currentEditingProductId) {
                // Update existing product
                await apiRequest(`/products/${currentEditingProductId}`, {
                    method: 'PUT',
                    body: JSON.stringify(data)
                });
                showToast('Producto actualizado exitosamente', 'success');
            } else {
                // Create new product
                await apiRequest('/products', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });
                showToast('Producto creado exitosamente', 'success');
                
                // Close form and reload catalog
                cancelProductForm();
            }
        } catch (error) {
            // Error shown by apiRequest
        }
    });

    // Add presentation form
    document.getElementById('add-presentation-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!currentEditingProductId) {
            showToast('Debe guardar el producto primero', 'error');
            return;
        }

        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        delete data.presentation_id; // Remove from data object

        try {
            if (currentEditingPresentationId) {
                // Update existing presentation
                await apiRequest(`/products/presentations/${currentEditingPresentationId}`, {
                    method: 'PUT',
                    body: JSON.stringify(data)
                });
                showToast('Presentación actualizada exitosamente', 'success');
                cancelPresentationEdit();
            } else {
                // Add new presentation
                await apiRequest(`/products/${currentEditingProductId}/presentations`, {
                    method: 'POST',
                    body: JSON.stringify(data)
                });
                showToast('Presentación agregada exitosamente', 'success');
                e.target.reset();
            }
            
            // Reload product to show updated presentations
            editProduct(currentEditingProductId);
        } catch (error) {
            // Error shown by apiRequest
        }
    });
});

// Product Form Helper Functions
function disableProductForm(productId) {
    // Disable all form inputs except barcode
    document.querySelector('#product-form input[name="name"]').disabled = true;
    document.querySelector('#product-form textarea[name="description"]').disabled = true;
    document.querySelector('#product-form select[name="category_id"]').disabled = true;
    document.getElementById('product-brand').disabled = true;
    document.getElementById('product-location').disabled = true;
    document.getElementById('product-supplier').disabled = true;
    
    // Disable save button and change its text
    const saveBtn = document.getElementById('save-product-btn');
    saveBtn.disabled = true;
    saveBtn.style.opacity = '0.5';
    saveBtn.style.cursor = 'not-allowed';
    saveBtn.textContent = '❌ Producto Ya Existe';
    
    // Add button to edit existing product
    let editExistingBtn = document.getElementById('edit-existing-product-btn');
    if (!editExistingBtn) {
        editExistingBtn = document.createElement('button');
        editExistingBtn.id = 'edit-existing-product-btn';
        editExistingBtn.type = 'button';
        editExistingBtn.className = 'btn btn-primary';
        editExistingBtn.style.float = 'right';
        editExistingBtn.style.marginRight = '8px';
        editExistingBtn.textContent = '✏️ Editar Producto Existente';
        editExistingBtn.onclick = function() {
            editProduct(productId);
        };
        saveBtn.parentElement.insertBefore(editExistingBtn, saveBtn);
    } else {
        editExistingBtn.style.display = 'inline-block';
        editExistingBtn.onclick = function() {
            editProduct(productId);
        };
    }
}

function enableProductForm() {
    // Enable all form inputs
    const nameInput = document.querySelector('#product-form input[name="name"]');
    const descInput = document.querySelector('#product-form textarea[name="description"]');
    const categorySelect = document.querySelector('#product-form select[name="category_id"]');
    const brandInput = document.getElementById('product-brand');
    const locationInput = document.getElementById('product-location');
    const supplierSelect = document.getElementById('product-supplier');
    
    if (nameInput) nameInput.disabled = false;
    if (descInput) descInput.disabled = false;
    if (categorySelect) categorySelect.disabled = false;
    if (brandInput) brandInput.disabled = false;
    if (locationInput) locationInput.disabled = false;
    if (supplierSelect) supplierSelect.disabled = false;
    
    // Enable save button and restore its text
    const saveBtn = document.getElementById('save-product-btn');
    if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.style.opacity = '1';
        saveBtn.style.cursor = 'pointer';
        saveBtn.textContent = 'Guardar Producto';
    }
    
    // Hide edit existing button
    const editExistingBtn = document.getElementById('edit-existing-product-btn');
    if (editExistingBtn) {
        editExistingBtn.style.display = 'none';
    }
}

// Utility Functions
function formatNumber(number) {
    return parseFloat(number).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('es-GT', options);
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return date.toLocaleDateString('es-GT', options);
}

// Search Input Clear Functions
function clearProductSearch() {
    const input = document.getElementById('product-search');
    const wrapper = document.getElementById('product-search-wrapper');
    const results = document.getElementById('search-results');
    
    input.value = '';
    wrapper.classList.remove('has-value');
    results.style.display = 'none';
    results.innerHTML = '';
    input.focus();
}

function clearCatalogSearch() {
    const input = document.getElementById('catalog-search');
    const wrapper = document.getElementById('catalog-search-wrapper');
    
    input.value = '';
    wrapper.classList.remove('has-value');
    input.focus();
    
    // Reload catalog without filter
    loadProductCatalog(1);
}

function clearStockBarcode() {
    const input = document.getElementById('stock-barcode');
    const wrapper = document.getElementById('stock-barcode-wrapper');
    
    if (!input || !wrapper) return;
    
    input.value = '';
    wrapper.classList.remove('has-value');
    
    // Hide product info
    const productInfo = document.getElementById('stock-product-info');
    if (productInfo) productInfo.style.display = 'none';
    
    const productId = document.getElementById('stock-product-id');
    if (productId) productId.value = '';
    
    const presentation = document.getElementById('stock-presentation');
    if (presentation) presentation.innerHTML = '<option value="">Primero busque el producto</option>';
    
    input.focus();
}

function clearCheckStockBarcode() {
    const input = document.getElementById('check-stock-barcode');
    const wrapper = document.getElementById('check-stock-barcode-wrapper');
    
    if (!input || !wrapper) return;
    
    input.value = '';
    wrapper.classList.remove('has-value');
    
    const productId = document.getElementById('check-stock-product-id');
    if (productId) productId.value = '';
    
    // Clear results
    const stockResult = document.getElementById('stock-result');
    if (stockResult) stockResult.innerHTML = '';
    
    const batchesList = document.getElementById('stock-batches-list');
    if (batchesList) batchesList.innerHTML = '';
    
    input.focus();
}

function loadStockProductInfo(product) {
    // Set product ID in hidden field
    document.getElementById('stock-product-id').value = product.id;
    
    // Show product info
    document.getElementById('stock-product-info').style.display = 'block';
    document.getElementById('stock-product-name').textContent = product.name;
    document.getElementById('stock-product-barcode').textContent = product.barcode;
    document.getElementById('stock-product-category').textContent = product.category_id ? 'ID: ' + product.category_id : 'Sin categoría';
    document.getElementById('stock-product-stock').textContent = product.total_stock + ' unidades';
    
    // Load presentations
    const presentationSelect = document.getElementById('stock-presentation');
    presentationSelect.innerHTML = '<option value="">Seleccione presentación</option>';
    
    if (product.presentations && product.presentations.length > 0) {
        product.presentations.forEach(function(p) {
            const option = document.createElement('option');
            option.value = p.id;
            option.textContent = p.name + ' (' + p.factor + ' unidades)';
            presentationSelect.appendChild(option);
        });
    } else {
        presentationSelect.innerHTML = '<option value="">Sin presentaciones disponibles</option>';
    }
}

function hideStockProductInfo() {
    const stockProductInfo = document.getElementById('stock-product-info');
    const stockProductId = document.getElementById('stock-product-id');
    const stockPresentation = document.getElementById('stock-presentation');
    
    if (stockProductInfo) stockProductInfo.style.display = 'none';
    if (stockProductId) stockProductId.value = '';
    if (stockPresentation) stockPresentation.innerHTML = '<option value="">Primero busque el producto</option>';
}

// Update search input wrappers to show/hide clear button
function setupSearchClearButtons() {
    const productSearch = document.getElementById('product-search');
    const productWrapper = document.getElementById('product-search-wrapper');
    
    if (productSearch && productWrapper) {
        productSearch.addEventListener('input', function() {
            if (this.value.trim().length > 0) {
                productWrapper.classList.add('has-value');
            } else {
                productWrapper.classList.remove('has-value');
            }
        });
    }
    
    const catalogSearch = document.getElementById('catalog-search');
    const catalogWrapper = document.getElementById('catalog-search-wrapper');
    
    if (catalogSearch && catalogWrapper) {
        catalogSearch.addEventListener('input', function() {
            if (this.value.trim().length > 0) {
                catalogWrapper.classList.add('has-value');
            } else {
                catalogWrapper.classList.remove('has-value');
            }
        });
    }
    
    const checkStockBarcode = document.getElementById('check-stock-barcode');
    const checkStockWrapper = document.getElementById('check-stock-barcode-wrapper');
    
    if (checkStockBarcode && checkStockWrapper) {
        checkStockBarcode.addEventListener('input', function() {
            if (this.value.trim().length > 0) {
                checkStockWrapper.classList.add('has-value');
            } else {
                checkStockWrapper.classList.remove('has-value');
            }
        });
    }
}

// Product Catalog Module
let catalogState = {
    currentPage: 1,
    lastPage: 1,
    perPage: 12
};

async function loadProductCatalog(page = 1) {
    try {
        const search = document.getElementById('catalog-search').value;
        const categoryId = document.getElementById('catalog-category-filter').value;
        
        const params = new URLSearchParams({
            per_page: catalogState.perPage,
            page: page
        });
        
        if (search) params.append('search', search);
        if (categoryId) params.append('category_id', categoryId);
        
        const response = await apiRequest(`/products/catalog?${params.toString()}`);
        const products = response.data;
        const pagination = response.pagination;
        
        catalogState.currentPage = pagination.current_page;
        catalogState.lastPage = pagination.last_page;
        
        renderProductCatalog(products);
        updateCatalogPagination();
    } catch (error) {
        document.getElementById('products-grid').innerHTML = '<p class="text-muted">Error al cargar productos</p>';
    }
}

function renderProductCatalog(products) {
    const grid = document.getElementById('products-grid');
    
    if (!products || products.length === 0) {
        grid.innerHTML = '<p class="text-muted">No se encontraron productos</p>';
        return;
    }
    
    grid.innerHTML = products.map(product => {
        const stockClass = product.total_stock > 50 ? 'high' : product.total_stock > 20 ? 'medium' : 'low';
        const locations = product.locations.length > 0 ? product.locations.join(', ') : 'Sin ubicación';
        const categoryBadge = product.category_name === 'Sin categoría' 
            ? '<span style="font-size: 11px; background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 4px;">🏷️ Sin categoría</span>'
            : `<span style="font-size: 11px; background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 4px;">🏷️ ${product.category_name}</span>`;
        
        const brandInfo = product.brand ? `<div style="font-size: 11px; color: #64748b; margin-top: 4px;">🏭 ${product.brand}</div>` : '';
        const locationInfo = product.location ? `<div style="font-size: 11px; color: #64748b; margin-top: 4px;">📍 ${product.location}</div>` : '';
        
        return `
            <div class="product-card" onclick="showProductDetails(${product.id})" style="cursor: pointer;">
                <div class="product-card-header">
                    <div style="flex: 1;">
                        <div class="product-card-title">${product.name}</div>
                        <div class="product-card-barcode">${product.barcode}</div>
                        <div style="margin-top: 4px;">${categoryBadge}</div>
                        ${brandInfo}
                        ${locationInfo}
                    </div>
                </div>
                <div class="product-card-price">Q ${formatNumber(product.price_with_iva)}</div>
                <div style="font-size: 12px; color: #64748b; margin-bottom: 8px;">
                    Q ${formatNumber(product.base_price)} / ${product.base_presentation_name || 'Unidad'} + IVA
                </div>
                <div class="product-card-stock">
                    <span>Stock: ${product.total_stock} unidades</span>
                    <span class="stock-badge ${stockClass}">${stockClass === 'high' ? 'Alto' : stockClass === 'medium' ? 'Medio' : 'Bajo'}</span>
                </div>
                <div style="font-size: 12px; color: #64748b; margin-top: 8px; padding-top: 8px; border-top: 1px solid #f1f5f9;">
                    🗄️ ${locations}
                </div>
                <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); editProduct(${product.id})" style="width: 100%; margin-top: 12px;">
                    ✏️ Editar
                </button>
            </div>
        `;
    }).join('');
}

function updateCatalogPagination() {
    const pagination = document.getElementById('catalog-pagination');
    const prevBtn = document.getElementById('prev-page-btn');
    const nextBtn = document.getElementById('next-page-btn');
    const pageInfo = document.getElementById('page-info');
    
    pagination.style.display = 'flex';
    pageInfo.textContent = `Página ${catalogState.currentPage} de ${catalogState.lastPage}`;
    
    prevBtn.disabled = catalogState.currentPage === 1;
    nextBtn.disabled = catalogState.currentPage === catalogState.lastPage;
}

function changeCatalogPage(direction) {
    if (direction === 'prev' && catalogState.currentPage > 1) {
        loadProductCatalog(catalogState.currentPage - 1);
    } else if (direction === 'next' && catalogState.currentPage < catalogState.lastPage) {
        loadProductCatalog(catalogState.currentPage + 1);
    }
}

// Product Details Modal
async function showProductDetails(productId) {
    const modal = document.getElementById('product-details-modal');
    const content = document.getElementById('product-details-content');
    
    modal.style.display = 'flex';
    content.innerHTML = '<p class="text-muted">Cargando información...</p>';
    
    try {
        const response = await apiRequest(`/products/${productId}`);
        const product = response.data;
        
        // Get stock batches
        const stockResponse = await apiRequest(`/stock/batches/${productId}`);
        const stockBatches = stockResponse.data || [];
        
        const categoryName = product.category_name || 'Sin categoría';
        const supplierName = product.supplier_name || 'Sin proveedor';
        const brand = product.brand || 'No especificada';
        const location = product.location || 'No especificada';
        
        content.innerHTML = `
            <div style="display: grid; gap: 24px;">
                <!-- Basic Information -->
                <div>
                    <h3 style="margin-bottom: 16px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">📦 Información Básica</h3>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                        <div>
                            <label style="font-weight: 600; color: #64748b; font-size: 12px; text-transform: uppercase;">Nombre</label>
                            <p style="margin: 4px 0 0 0; font-size: 16px; color: #1e293b;">${product.name}</p>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #64748b; font-size: 12px; text-transform: uppercase;">Código de Barras</label>
                            <p style="margin: 4px 0 0 0; font-size: 16px; font-family: monospace; color: #1e293b;">${product.barcode}</p>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #64748b; font-size: 12px; text-transform: uppercase;">Categoría</label>
                            <p style="margin: 4px 0 0 0; font-size: 14px; color: #1e293b;">🏷️ ${categoryName}</p>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #64748b; font-size: 12px; text-transform: uppercase;">Marca</label>
                            <p style="margin: 4px 0 0 0; font-size: 14px; color: #1e293b;">🏭 ${brand}</p>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #64748b; font-size: 12px; text-transform: uppercase;">Ubicación</label>
                            <p style="margin: 4px 0 0 0; font-size: 14px; color: #1e293b;">📍 ${location}</p>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #64748b; font-size: 12px; text-transform: uppercase;">Proveedor</label>
                            <p style="margin: 4px 0 0 0; font-size: 14px; color: #1e293b;">🏢 ${supplierName}</p>
                        </div>
                    </div>
                    ${product.description ? `
                        <div style="margin-top: 16px;">
                            <label style="font-weight: 600; color: #64748b; font-size: 12px; text-transform: uppercase;">Descripción</label>
                            <p style="margin: 4px 0 0 0; font-size: 14px; color: #475569;">${product.description}</p>
                        </div>
                    ` : ''}
                </div>
                
                <!-- Presentations -->
                <div>
                    <h3 style="margin-bottom: 16px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">💰 Presentaciones y Precios</h3>
                    ${product.presentations && product.presentations.length > 0 ? `
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Presentación</th>
                                    <th>Unidades</th>
                                    <th>Precio Compra</th>
                                    <th>Precio Venta</th>
                                    <th>Precio c/IVA</th>
                                    <th>Margen</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${product.presentations.map(p => {
                                    const margin = ((p.sale_price - p.purchase_price) / p.purchase_price * 100).toFixed(1);
                                    const marginColor = margin > 30 ? '#10b981' : margin > 15 ? '#f59e0b' : '#64748b';
                                    return `
                                        <tr>
                                            <td><strong>${p.name}</strong></td>
                                            <td>${p.factor}</td>
                                            <td>Q ${formatNumber(p.purchase_price)}</td>
                                            <td>Q ${formatNumber(p.sale_price)}</td>
                                            <td>Q ${formatNumber(p.sale_price * 1.12)}</td>
                                            <td style="color: ${marginColor}; font-weight: 600;">${margin}%</td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    ` : '<p class="text-muted">No hay presentaciones registradas</p>'}
                </div>
                
                <!-- Stock Batches -->
                <div>
                    <h3 style="margin-bottom: 16px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">📊 Inventario por Lotes</h3>
                    ${stockBatches.length > 0 ? `
                        <div style="margin-bottom: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; border-left: 4px solid #3b82f6;">
                            <strong style="font-size: 18px; color: #1e293b;">Total Disponible: ${stockBatches.reduce((sum, b) => sum + b.quantity_available, 0)} unidades</strong>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Lote</th>
                                    <th>Ubicación</th>
                                    <th>Cantidad Disponible</th>
                                    <th>Cantidad Inicial</th>
                                    <th>Vencimiento</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${stockBatches.map(batch => {
                                    const expDate = batch.expiration_date ? formatDate(batch.expiration_date) : 'Sin vencimiento';
                                    const stockPerc = (batch.quantity_available / batch.quantity_initial * 100).toFixed(0);
                                    const stockColor = stockPerc > 50 ? '#10b981' : stockPerc > 20 ? '#f59e0b' : '#ef4444';
                                    return `
                                        <tr>
                                            <td><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 13px;">${batch.batch_number}</code></td>
                                            <td>📍 ${batch.location || 'No especificada'}</td>
                                            <td>
                                                <strong style="color: ${stockColor};">${batch.quantity_available}</strong>
                                                <span style="color: #94a3b8; font-size: 12px;"> (${stockPerc}%)</span>
                                            </td>
                                            <td>${batch.quantity_initial}</td>
                                            <td>${expDate}</td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    ` : '<p class="text-muted">No hay stock disponible</p>'}
                </div>
            </div>
        `;
    } catch (error) {
        content.innerHTML = `<p style="color: #ef4444;">Error al cargar la información del producto</p>`;
    }
}

function closeProductDetails() {
    document.getElementById('product-details-modal').style.display = 'none';
}

// Category Management Functions
async function openCategoryModal() {
    const modal = document.getElementById('category-modal');
    modal.style.display = 'flex';
    resetCategoryForm();
    await loadCategoriesList();
}

function closeCategoryModal() {
    document.getElementById('category-modal').style.display = 'none';
    resetCategoryForm();
}

function resetCategoryForm() {
    document.getElementById('category-id').value = '';
    document.getElementById('category-name').value = '';
    document.getElementById('category-description').value = '';
    document.getElementById('category-form-title').textContent = 'Nueva Categoría';
    document.getElementById('save-category-btn').textContent = 'Crear Categoría';
    document.getElementById('cancel-category-btn').style.display = 'none';
}

async function loadCategoriesList() {
    const listContainer = document.getElementById('categories-list');
    try {
        const response = await apiRequest('/categories');
        const categories = response.data;
        
        if (categories.length === 0) {
            listContainer.innerHTML = '<p class=\"text-muted\">No hay categorías creadas. Crea tu primera categoría arriba.</p>';
            return;
        }
        
        listContainer.innerHTML = categories.map(category => `
            <div class=\"category-card\">
                <div class=\"category-info\">
                    <div class=\"category-name\">${category.name}</div>
                    ${category.description ? `<div class=\"category-description\">${category.description}</div>` : ''}
                    <div class=\"category-count\">${category.products_count || 0} producto(s)</div>
                </div>
                <div class=\"category-actions\">
                    <button class=\"btn-icon btn-icon-edit\" onclick=\"editCategory(${category.id})\" title=\"Editar\">
                        ✏
                    </button>
                    <button class=\"btn-icon btn-icon-delete\" onclick=\"deleteCategory(${category.id}, ${category.products_count || 0})\" title=\"Eliminar\">
                        🗑
                    </button>
                </div>
            </div>
        `).join('');
    } catch (error) {
        listContainer.innerHTML = '<p style=\"color: #ef4444;\">Error al cargar las categorías</p>';
    }
}

async function editCategory(categoryId) {
    try {
        const response = await apiRequest(`/categories`);
        const category = response.data.find(c => c.id === categoryId);
        
        if (!category) {
            showNotification('Categoría no encontrada', 'error');
            return;
        }
        
        document.getElementById('category-id').value = category.id;
        document.getElementById('category-name').value = category.name;
        document.getElementById('category-description').value = category.description || '';
        document.getElementById('category-form-title').textContent = 'Editar Categoría';
        document.getElementById('save-category-btn').textContent = 'Guardar Cambios';
        document.getElementById('cancel-category-btn').style.display = 'inline-block';
        
        // Scroll to form
        document.getElementById('category-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
        showNotification('Error al cargar la categoría', 'error');
    }
}

async function deleteCategory(categoryId, productsCount) {
    if (productsCount > 0) {
        showNotification(`No se puede eliminar esta categoría porque tiene ${productsCount} producto(s) asociados`, 'error');
        return;
    }
    
    if (!confirm('¿Estás seguro de que deseas eliminar esta categoría?')) {
        return;
    }
    
    try {
        await apiRequest(`/categories/${categoryId}`, {
            method: 'DELETE'
        });
        showNotification('Categoría eliminada exitosamente', 'success');
        await loadCategoriesList();
        await loadProductCategories(); // Refresh category dropdowns
    } catch (error) {
        showNotification(error.message || 'Error al eliminar la categoría', 'error');
    }
}

// Handle category form submission
document.addEventListener('DOMContentLoaded', () => {
    const categoryForm = document.getElementById('category-form');
    if (categoryForm) {
        categoryForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const categoryId = document.getElementById('category-id').value;
            const name = document.getElementById('category-name').value.trim();
            const description = document.getElementById('category-description').value.trim();
            
            if (!name) {
                showNotification('El nombre de la categoría es requerido', 'error');
                return;
            }
            
            try {
                const url = categoryId ? `/categories/${categoryId}` : '/categories';
                const method = categoryId ? 'PUT' : 'POST';
                
                await apiRequest(url, {
                    method,
                    body: JSON.stringify({ name, description })
                });
                
                showNotification(
                    categoryId ? 'Categoría actualizada exitosamente' : 'Categoría creada exitosamente',
                    'success'
                );
                
                resetCategoryForm();
                await loadCategoriesList();
                await loadProductCategories(); // Refresh category dropdowns
            } catch (error) {
                showNotification(error.message || 'Error al guardar la categoría', 'error');
            }
        });
    }
    
    // Close category modal on click outside
    const categoryModal = document.getElementById('category-modal');
    if (categoryModal) {
        categoryModal.addEventListener('click', (e) => {
            if (e.target === categoryModal) {
                closeCategoryModal();
            }
        });
    }
});

// Supplier Management Functions
async function openSupplierModal() {
    const modal = document.getElementById('supplier-modal');
    modal.style.display = 'flex';
    resetSupplierForm();
    await loadSuppliersList();
}

function closeSupplierModal() {
    document.getElementById('supplier-modal').style.display = 'none';
    resetSupplierForm();
}

function resetSupplierForm() {
    document.getElementById('supplier-id').value = '';
    document.getElementById('supplier-name').value = '';
    document.getElementById('supplier-contact-name').value = '';
    document.getElementById('supplier-phone').value = '';
    document.getElementById('supplier-email').value = '';
    document.getElementById('supplier-address').value = '';
    document.getElementById('supplier-form-title').textContent = 'Nuevo Proveedor';
    document.getElementById('save-supplier-btn').textContent = 'Crear Proveedor';
    document.getElementById('cancel-supplier-btn').style.display = 'none';
}

async function loadSuppliersList() {
    const listContainer = document.getElementById('suppliers-list');
    try {
        const response = await apiRequest('/suppliers');
        const suppliers = response.data;
        
        if (suppliers.length === 0) {
            listContainer.innerHTML = '<p class="text-muted">No hay proveedores creados. Crea tu primer proveedor arriba.</p>';
            return;
        }
        
        listContainer.innerHTML = suppliers.map(supplier => {
            const contactInfo = [];
            if (supplier.contact_name) contactInfo.push(`Contacto: ${supplier.contact_name}`);
            if (supplier.phone) contactInfo.push(`Tel: ${supplier.phone}`);
            if (supplier.email) contactInfo.push(`Email: ${supplier.email}`);
            
            return `
            <div class="category-card">
                <div class="category-info">
                    <div class="category-name">${supplier.name}</div>
                    ${contactInfo.length > 0 ? `<div class="category-description">${contactInfo.join(' • ')}</div>` : ''}
                    ${supplier.address ? `<div class="category-description" style="font-size: 12px; color: #94a3b8;">📍 ${supplier.address}</div>` : ''}
                    <div class="category-count">${supplier.products_count || 0} producto(s)</div>
                </div>
                <div class="category-actions">
                    <button class="btn-icon btn-icon-edit" onclick="editSupplier(${supplier.id})" title="Editar">
                        ✏
                    </button>
                    <button class="btn-icon btn-icon-delete" onclick="deleteSupplier(${supplier.id}, ${supplier.products_count || 0})" title="Eliminar">
                        🗑
                    </button>
                </div>
            </div>
        `;
        }).join('');
    } catch (error) {
        listContainer.innerHTML = '<p style="color: #ef4444;">Error al cargar los proveedores</p>';
    }
}

async function editSupplier(supplierId) {
    try {
        const response = await apiRequest(`/suppliers/${supplierId}`);
        const supplier = response.data;
        
        if (!supplier) {
            showNotification('Proveedor no encontrado', 'error');
            return;
        }
        
        document.getElementById('supplier-id').value = supplier.id;
        document.getElementById('supplier-name').value = supplier.name;
        document.getElementById('supplier-contact-name').value = supplier.contact_name || '';
        document.getElementById('supplier-phone').value = supplier.phone || '';
        document.getElementById('supplier-email').value = supplier.email || '';
        document.getElementById('supplier-address').value = supplier.address || '';
        document.getElementById('supplier-form-title').textContent = 'Editar Proveedor';
        document.getElementById('save-supplier-btn').textContent = 'Guardar Cambios';
        document.getElementById('cancel-supplier-btn').style.display = 'inline-block';
        
        // Scroll to form
        document.getElementById('supplier-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
        showNotification('Error al cargar el proveedor', 'error');
    }
}

async function deleteSupplier(supplierId, productsCount) {
    if (productsCount > 0) {
        showNotification(`No se puede eliminar este proveedor porque tiene ${productsCount} producto(s) asociados`, 'error');
        return;
    }
    
    if (!confirm('¿Estás seguro de que deseas eliminar este proveedor?')) {
        return;
    }
    
    try {
        await apiRequest(`/suppliers/${supplierId}`, {
            method: 'DELETE'
        });
        showNotification('Proveedor eliminado exitosamente', 'success');
        await loadSuppliersList();
        await loadSuppliers(); // Refresh supplier dropdown
    } catch (error) {
        showNotification(error.message || 'Error al eliminar el proveedor', 'error');
    }
}

// Handle supplier form submission
document.addEventListener('DOMContentLoaded', () => {
    const supplierForm = document.getElementById('supplier-form');
    if (supplierForm) {
        supplierForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const supplierId = document.getElementById('supplier-id').value;
            const name = document.getElementById('supplier-name').value.trim();
            const contact_name = document.getElementById('supplier-contact-name').value.trim();
            const phone = document.getElementById('supplier-phone').value.trim();
            const email = document.getElementById('supplier-email').value.trim();
            const address = document.getElementById('supplier-address').value.trim();
            
            if (!name) {
                showNotification('El nombre del proveedor es requerido', 'error');
                return;
            }
            
            try {
                const url = supplierId ? `/suppliers/${supplierId}` : '/suppliers';
                const method = supplierId ? 'PUT' : 'POST';
                
                await apiRequest(url, {
                    method,
                    body: JSON.stringify({ name, contact_name, phone, email, address, active: true })
                });
                
                showNotification(
                    supplierId ? 'Proveedor actualizado exitosamente' : 'Proveedor creado exitosamente',
                    'success'
                );
                
                resetSupplierForm();
                await loadSuppliersList();
                await loadSuppliers(); // Refresh supplier dropdown
            } catch (error) {
                showNotification(error.message || 'Error al guardar el proveedor', 'error');
            }
        });
    }
    
    // Close supplier modal on click outside
    const supplierModal = document.getElementById('supplier-modal');
    if (supplierModal) {
        supplierModal.addEventListener('click', (e) => {
            if (e.target === supplierModal) {
                closeSupplierModal();
            }
        });
    }
});

async function loadProductCategories() {
    try {
        const response = await apiRequest('/products/categories');
        const categories = response.data;
        
        // Update catalog filter
        const catalogSelect = document.getElementById('catalog-category-filter');
        if (catalogSelect) {
            catalogSelect.innerHTML = '<option value="">Todas las categorías</option>';
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.name;
                catalogSelect.appendChild(option);
            });
        }

        // Update product form category select
        const formSelect = document.getElementById('product-category');
        if (formSelect) {
            formSelect.innerHTML = '<option value="">Sin categoría</option>';
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.name;
                formSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

async function loadSuppliers() {
    try {
        const response = await apiRequest('/suppliers');
        const suppliers = response.data;
        
        const formSelect = document.getElementById('product-supplier');
        if (formSelect) {
            formSelect.innerHTML = '<option value="">Sin proveedor</option>';
            suppliers.forEach(supplier => {
                const option = document.createElement('option');
                option.value = supplier.id;
                option.textContent = supplier.name;
                formSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading suppliers:', error);
    }
}

// Product CRUD Functions
let currentEditingProductId = null;
let currentEditingPresentationId = null;

function showNewProductForm() {
    currentEditingProductId = null;
    document.getElementById('product-form-section').style.display = 'block';
    document.getElementById('catalog-view-section').style.display = 'none';
    document.getElementById('product-form-title').textContent = 'Nuevo Producto';
    document.getElementById('product-form').reset();
    document.getElementById('product-id').value = '';
    document.getElementById('presentations-section').style.display = 'none';
    document.getElementById('save-product-btn').textContent = 'Guardar Producto';
    
    // Reset form state (enable all fields)
    enableProductForm();
    
    // Toggle button
    document.getElementById('new-product-btn').style.display = 'none';
}

function cancelProductForm() {
    currentEditingProductId = null;
    document.getElementById('product-form-section').style.display = 'none';
    document.getElementById('catalog-view-section').style.display = 'block';
    
    // Reset form state
    enableProductForm();
    
    // Toggle button
    document.getElementById('new-product-btn').style.display = 'inline-block';
    
    loadProductCatalog();
}

async function editProduct(productId) {
    try {
        currentEditingProductId = productId;
        const response = await apiRequest(`/products/${productId}`);
        const product = response.data;

        // Show form
        document.getElementById('product-form-section').style.display = 'block';
        document.getElementById('catalog-view-section').style.display = 'none';
        document.getElementById('product-form-title').textContent = 'Editar Producto';
        document.getElementById('save-product-btn').textContent = 'Actualizar Producto';
        
        // Toggle button
        document.getElementById('new-product-btn').style.display = 'none';

        // Fill form
        document.getElementById('product-id').value = product.id;
        document.getElementById('product-barcode').value = product.barcode;
        document.querySelector('#product-form input[name="name"]').value = product.name;
        document.querySelector('#product-form textarea[name="description"]').value = product.description || '';
        document.querySelector('#product-form select[name="category_id"]').value = product.category_id || '';
        document.getElementById('product-brand').value = product.brand || '';
        document.getElementById('product-location').value = product.location || '';
        document.getElementById('product-supplier').value = product.supplier_id || '';

        // Show presentations section
        document.getElementById('presentations-section').style.display = 'block';
        renderPresentations(product.presentations);
    } catch (error) {
        showToast('Error al cargar el producto', 'error');
    }
}

function renderPresentations(presentations) {
    const container = document.getElementById('presentations-list');
    
    if (!presentations || presentations.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay presentaciones agregadas</p>';
        return;
    }

    container.innerHTML = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Precio Compra</th>
                    <th>Precio Venta</th>
                    <th>Precio c/IVA</th>
                    <th>Unidades</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                ${presentations.map(p => `
                    <tr>
                        <td>${p.name}</td>
                        <td>Q ${formatNumber(p.purchase_price)}</td>
                        <td>Q ${formatNumber(p.sale_price)}</td>
                        <td>Q ${formatNumber(p.sale_price * 1.12)}</td>
                        <td>${p.factor || 1}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editPresentation(${p.id}, '${p.name}', ${p.purchase_price}, ${p.sale_price}, ${p.factor || 1})" style="margin-right: 5px;">Editar</button>
                            <button class="btn btn-sm btn-danger" onclick="deletePresentation(${p.id})">Eliminar</button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function editPresentation(presentationId, name, purchasePrice, salePrice, factor) {
    currentEditingPresentationId = presentationId;
    
    // Update form title and button
    document.getElementById('presentation-form-title').textContent = 'Editar Presentación';
    document.getElementById('save-presentation-btn').textContent = 'Actualizar Presentación';
    document.getElementById('cancel-presentation-btn').style.display = 'inline-block';
    
    // Fill form
    document.getElementById('presentation-id').value = presentationId;
    document.getElementById('presentation-name').value = name;
    document.getElementById('presentation-purchase-price').value = purchasePrice;
    document.getElementById('presentation-sale-price').value = salePrice;
    document.getElementById('presentation-factor').value = factor;
    
    // Scroll to form
    document.getElementById('add-presentation-form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function cancelPresentationEdit() {
    currentEditingPresentationId = null;
    
    // Reset form
    document.getElementById('add-presentation-form').reset();
    document.getElementById('presentation-id').value = '';
    
    // Update form title and button
    document.getElementById('presentation-form-title').textContent = 'Agregar Nueva Presentación';
    document.getElementById('save-presentation-btn').textContent = 'Agregar Presentación';
    document.getElementById('cancel-presentation-btn').style.display = 'none';
}

async function deletePresentation(presentationId) {
    if (!confirm('¿Está seguro de eliminar esta presentación?')) return;

    try {
        await apiRequest(`/products/presentations/${presentationId}`, {
            method: 'DELETE'
        });
        showToast('Presentación eliminada', 'success');
        
        // Reload product
        if (currentEditingProductId) {
            editProduct(currentEditingProductId);
        }
    } catch (error) {
        // Error shown by apiRequest
    }
}

// Initialize catalog search with real-time filtering
if (document.getElementById('catalog-search')) {
    let catalogSearchTimeout;
    document.getElementById('catalog-search').addEventListener('input', (e) => {
        clearTimeout(catalogSearchTimeout);
        catalogSearchTimeout = setTimeout(() => {
            loadProductCatalog(1);
        }, 500);
    });
}

// Initialize category filter with instant search
if (document.getElementById('catalog-category-filter')) {
    document.getElementById('catalog-category-filter').addEventListener('change', (e) => {
        loadProductCatalog(1);
    });
}

// ==================== INVENTORY IMPORT ====================

let currentImportId = null;

function openImportModal() {
    document.getElementById('import-modal').style.display = 'flex';
    resetImport();
}

function closeImportModal() {
    document.getElementById('import-modal').style.display = 'none';
    resetImport();
}

function resetImport() {
    currentImportId = null;
    document.getElementById('upload-section').style.display = 'block';
    document.getElementById('preview-section').style.display = 'none';
    document.getElementById('import-loading').style.display = 'none';
    document.getElementById('import-file-input').value = '';
    document.getElementById('import-source-name').value = '';
}

// Handle file selection
document.getElementById('import-file-input')?.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    if (!file.name.endsWith('.json')) {
        showNotification('Por favor selecciona un archivo JSON válido', 'error');
        return;
    }

    // Show loading
    document.getElementById('upload-section').style.display = 'none';
    document.getElementById('import-loading').style.display = 'block';

    try {
        // Read file to detect format
        const fileText = await file.text();
        const jsonData = JSON.parse(fileText);
        
        // Detect if it's category-based format
        const isCategoryFormat = jsonData.hasOwnProperty('categoria') && jsonData.hasOwnProperty('items');
        const isMultiCategoryFormat = Array.isArray(jsonData) && jsonData.length > 0 && jsonData[0].hasOwnProperty('categoria');
        
        // Choose appropriate endpoint
        const endpoint = (isCategoryFormat || isMultiCategoryFormat) 
            ? '/api/inventory/import/preview-category'
            : '/api/inventory/import/preview';
        
        const formData = new FormData();
        formData.append('file', file);

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            },
            body: formData
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || 'Error al procesar el archivo');
        }

        // Show preview
        showImportPreview(result.data);
    } catch (error) {
        showNotification(error.message, 'error');
        resetImport();
    }
});

function showImportPreview(previewData) {
    document.getElementById('import-loading').style.display = 'none';
    document.getElementById('preview-section').style.display = 'block';

    currentImportId = previewData.import_id;

    // Build stats HTML
    const statsHTML = `
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px;">
            <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                <div style="font-size: 24px; font-weight: bold; color: #10b981;">${previewData.total_products}</div>
                <div style="font-size: 13px; color: #64748b;">Total Productos</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                <div style="font-size: 24px; font-weight: bold; color: #3b82f6;">${previewData.new_products}</div>
                <div style="font-size: 13px; color: #64748b;">Nuevos</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                <div style="font-size: 24px; font-weight: bold; color: #f59e0b;">${previewData.existing_products}</div>
                <div style="font-size: 13px; color: #64748b;">Existentes (se actualizarán)</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                <div style="font-size: 24px; font-weight: bold; color: #8b5cf6;">${previewData.total_presentations}</div>
                <div style="font-size: 13px; color: #64748b;">Presentaciones</div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                <div style="font-size: 24px; font-weight: bold; color: #06b6d4;">${previewData.total_stock_batches}</div>
                <div style="font-size: 13px; color: #64748b;">Lotes de Stock</div>
            </div>
        </div>
    `;

    document.getElementById('preview-stats').innerHTML = statsHTML;

    // Show errors
    if (previewData.errors && previewData.errors.length > 0) {
        const errorsHTML = previewData.errors.map(error => `<li style="color: #dc2626;">${error}</li>`).join('');
        document.getElementById('preview-warnings').style.display = 'block';
        document.getElementById('preview-warnings').innerHTML = `
            <h4 style="color: #dc2626; margin-bottom: 8px;">❌ Errores</h4>
            <ul style="margin: 0; padding-left: 20px;">${errorsHTML}</ul>
        `;
        document.getElementById('commit-import-btn').disabled = true;
        document.getElementById('commit-import-btn').style.opacity = '0.5';
        return;
    }

    // Show warnings
    if (previewData.warnings && previewData.warnings.length > 0) {
        const warningsHTML = previewData.warnings.map(warning => `<li>${warning}</li>`).join('');
        document.getElementById('preview-warnings').style.display = 'block';
        document.getElementById('preview-warnings-list').innerHTML = warningsHTML;
    } else {
        document.getElementById('preview-warnings').style.display = 'none';
    }

    // Enable commit button
    document.getElementById('commit-import-btn').disabled = false;
    document.getElementById('commit-import-btn').style.opacity = '1';
}

async function commitImport() {
    if (!currentImportId) {
        showNotification('No hay importación pendiente', 'error');
        return;
    }

    if (!confirm('¿Estás seguro de que deseas importar estos productos? Esta acción no se puede deshacer.')) {
        return;
    }

    // Show loading
    document.getElementById('preview-section').style.display = 'none';
    document.getElementById('import-loading').style.display = 'block';
    document.getElementById('import-loading').querySelector('p').textContent = 'Importando productos...';

    // Get source name from input
    const sourceName = document.getElementById('import-source-name').value.trim();

    try {
        const response = await apiRequest('/inventory/import/commit', {
            method: 'POST',
            body: JSON.stringify({ 
                import_id: currentImportId,
                source_name: sourceName || null,
                source_type: 'json'
            })
        });

        showNotification(
            `Importación completada: ${response.data.summary.products_created} creados, ${response.data.summary.products_updated} actualizados`,
            'success'
        );

        closeImportModal();
        
        // Reload catalog
        if (typeof loadProductCatalog === 'function') {
            loadProductCatalog(1);
        }
        
        // Reload categories and suppliers if new ones were created
        if (response.data.summary.categories_created > 0) {
            await loadProductCategories();
        }
        if (response.data.summary.suppliers_created > 0) {
            await loadSuppliers();
        }
    } catch (error) {
        showNotification(error.message || 'Error al importar', 'error');
        resetImport();
    }
}

// Close import modal on click outside
document.getElementById('import-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'import-modal') {
        closeImportModal();
    }
});

// Format example switcher
function showFormatExample(format) {
    // Hide all examples
    document.getElementById('format-example-standard').style.display = 'none';
    document.getElementById('format-example-category').style.display = 'none';
    
    // Reset all buttons
    document.getElementById('btn-format-standard').style.borderBottom = '2px solid transparent';
    document.getElementById('btn-format-standard').style.color = '#64748b';
    document.getElementById('btn-format-standard').style.fontWeight = 'normal';
    document.getElementById('btn-format-category').style.borderBottom = '2px solid transparent';
    document.getElementById('btn-format-category').style.color = '#64748b';
    document.getElementById('btn-format-category').style.fontWeight = 'normal';
    
    // Show selected example
    if (format === 'standard') {
        document.getElementById('format-example-standard').style.display = 'block';
        document.getElementById('btn-format-standard').style.borderBottom = '2px solid #3b82f6';
        document.getElementById('btn-format-standard').style.color = '#3b82f6';
        document.getElementById('btn-format-standard').style.fontWeight = '600';
    } else if (format === 'category') {
        document.getElementById('format-example-category').style.display = 'block';
        document.getElementById('btn-format-category').style.borderBottom = '2px solid #3b82f6';
        document.getElementById('btn-format-category').style.color = '#3b82f6';
        document.getElementById('btn-format-category').style.fontWeight = '600';
    }
}
// ==================== IMPORT HISTORY ====================

let currentHistoryPage = 1;

async function openImportHistoryModal() {
    document.getElementById('import-history-modal').style.display = 'flex';
    currentHistoryPage = 1;
    await loadImportHistory();
}

function closeImportHistoryModal() {
    document.getElementById('import-history-modal').style.display = 'none';
}

async function loadImportHistory(page) {
    if (!page) page = 1;
    
    document.getElementById('import-history-loading').style.display = 'block';
    document.getElementById('import-history-content').style.display = 'none';

    try {
        const response = await apiRequest('/inventory/import/history?page=' + page);
        
        const tbody = document.getElementById('import-history-tbody');
        tbody.innerHTML = '';

        if (response.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">No hay historial de importaciones</td></tr>';
        } else {
            for (let i = 0; i < response.data.length; i++) {
                const item = response.data[i];
                const row = document.createElement('tr');
                
                const date = new Date(item.imported_at);
                const formattedDate = date.toLocaleDateString('es-GT', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                const typeColors = {
                    'json': '#3b82f6',
                    'excel': '#10b981',
                    'manual': '#8b5cf6'
                };
                
                const typeColor = typeColors[item.source_type] || '#64748b';
                const typeText = item.source_type ? item.source_type.toUpperCase() : 'DESCONOCIDO';
                
                let productsUpdatedHTML = '';
                if (item.total_products_updated > 0) {
                    productsUpdatedHTML = ' <span style="color: #f59e0b; font-weight: 600;">~' + item.total_products_updated + '</span>';
                }
                
                let categoriesHTML = '-';
                if (item.total_categories_created > 0) {
                    categoriesHTML = '<span style="color: #8b5cf6;">+' + item.total_categories_created + '</span>';
                }
                
                const userName = item.user ? item.user.name : 'N/A';

                row.innerHTML = '<td>' + formattedDate + '</td>' +
                    '<td><strong>' + item.source_name + '</strong></td>' +
                    '<td><span style="background: ' + typeColor + '; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px;">' + typeText + '</span></td>' +
                    '<td><span style="color: #10b981; font-weight: 600;">+' + item.total_products_created + '</span>' + productsUpdatedHTML + '</td>' +
                    '<td>' + categoriesHTML + '</td>' +
                    '<td>' + userName + '</td>';
                
                tbody.appendChild(row);
            }
        }

        const pagination = document.getElementById('import-history-pagination');
        pagination.innerHTML = '';
        
        if (response.last_page > 1) {
            if (response.current_page > 1) {
                const prevBtn = document.createElement('button');
                prevBtn.textContent = '← Anterior';
                prevBtn.className = 'btn btn-secondary';
                prevBtn.style.marginRight = '8px';
                const prevPage = response.current_page - 1;
                prevBtn.onclick = function() { 
                    loadImportHistory(prevPage);
                };
                pagination.appendChild(prevBtn);
            }
            
            const pageInfo = document.createElement('span');
            pageInfo.textContent = 'Página ' + response.current_page + ' de ' + response.last_page;
            pageInfo.style.margin = '0 16px';
            pagination.appendChild(pageInfo);
            
            if (response.current_page < response.last_page) {
                const nextBtn = document.createElement('button');
                nextBtn.textContent = 'Siguiente →';
                nextBtn.className = 'btn btn-secondary';
                nextBtn.style.marginLeft = '8px';
                const nextPage = response.current_page + 1;
                nextBtn.onclick = function() { 
                    loadImportHistory(nextPage);
                };
                pagination.appendChild(nextBtn);
            }
        }

        document.getElementById('import-history-loading').style.display = 'none';
        document.getElementById('import-history-content').style.display = 'block';

    } catch (error) {
        showNotification('Error al cargar historial: ' + error.message, 'error');
        document.getElementById('import-history-loading').style.display = 'none';
    }
}

if (document.getElementById('import-history-modal')) {
    document.getElementById('import-history-modal').addEventListener('click', function(e) {
        if (e.target.id === 'import-history-modal') {
            closeImportHistoryModal();
        }
    });
}

// ==================== SALES HISTORY AND CURRENT CASH BOX ====================

async function loadCurrentCashBoxSales() {
    try {
        const response = await apiRequest('/sales/current-cash-box');
        const sales = response.data;
        const cashBox = response.cash_box;
        
        const container = document.getElementById('current-cashbox-sales-list');
        const totalsDiv = document.getElementById('current-cashbox-totals');
        
        if (sales.length === 0) {
            container.innerHTML = '<p class="text-muted">No hay ventas registradas en esta caja</p>';
            totalsDiv.style.display = 'none';
            return;
        }
        
        // Calculate totals
        let totalAmount = 0;
        sales.forEach(sale => {
            if (sale.status === 'completed') {
                totalAmount += parseFloat(sale.total);
            }
        });
        
        // Display sales table
        container.innerHTML = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>NIT</th>
                        <th>Total</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    ${sales.map(sale => `
                        <tr>
                            <td>#${sale.id}</td>
                            <td>${new Date(sale.created_at).toLocaleString('es-GT')}</td>
                            <td>${sale.customer_name || 'N/A'}</td>
                            <td>${sale.customer_nit || 'CF'}</td>
                            <td>Q ${parseFloat(sale.total).toFixed(2)}</td>
                            <td>
                                <span class="badge badge-${sale.status === 'completed' ? 'success' : sale.status === 'annulled' ? 'danger' : 'warning'}">
                                    ${sale.status === 'completed' ? 'Entregado' : sale.status === 'annulled' ? 'Anulado' : 'Pendiente'}
                                </span>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
        
        // Display totals
        document.getElementById('cashbox-sales-count').textContent = sales.filter(s => s.status === 'completed').length;
        document.getElementById('cashbox-sales-total').textContent = totalAmount.toFixed(2);
        totalsDiv.style.display = 'block';
        
    } catch (error) {
        console.error('Error loading current cash box sales:', error);
    }
}

function initializeSalesHistory() {
    // Populate year filter
    const yearSelect = document.getElementById('sales-year-filter');
    const currentYear = new Date().getFullYear();
    const startYear = 2024; // Adjust based on your needs
    
    yearSelect.innerHTML = '';
    for (let year = currentYear; year >= startYear; year--) {
        const option = document.createElement('option');
        option.value = year;
        option.textContent = year;
        yearSelect.appendChild(option);
    }
    
    // Add event listener for year change
    yearSelect.addEventListener('change', function() {
        loadSalesHistory();
    });
    
    // Add event listeners for month buttons
    const monthButtons = document.querySelectorAll('.month-btn');
    monthButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active class from all buttons
            monthButtons.forEach(b => b.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
            // Load sales with new filter
            loadSalesHistory();
        });
    });
    
    // Load initial data
    loadSalesHistory();
}

async function loadSalesHistory() {
    try {
        const year = document.getElementById('sales-year-filter').value;
        const activeMonthBtn = document.querySelector('.month-btn.active');
        const month = activeMonthBtn ? activeMonthBtn.getAttribute('data-month') : '';
        
        let url = `/sales?year=${year}`;
        if (month) {
            url += `&month=${month}`;
        }
        
        const response = await apiRequest(url);
        const sales = response.data;
        
        const container = document.getElementById('sales-history-list');
        const totalsDiv = document.getElementById('sales-history-totals');
        
        if (sales.length === 0) {
            container.innerHTML = '<p class="text-muted">No hay ventas en este período</p>';
            totalsDiv.style.display = 'none';
            return;
        }
        
        // Calculate totals
        let totalAmount = 0;
        sales.forEach(sale => {
            if (sale.status === 'completed') {
                totalAmount += parseFloat(sale.total);
            }
        });
        
        // Display sales table
        container.innerHTML = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>NIT</th>
                        <th>Productos</th>
                        <th>Total</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    ${sales.map(sale => `
                        <tr>
                            <td>#${sale.id}</td>
                            <td>${new Date(sale.created_at).toLocaleString('es-GT')}</td>
                            <td>${sale.customer_name || 'N/A'}</td>
                            <td>${sale.customer_nit || 'CF'}</td>
                            <td>${sale.items ? sale.items.length : 0} productos</td>
                            <td>Q ${parseFloat(sale.total).toFixed(2)}</td>
                            <td>
                                <span class="badge badge-${sale.status === 'completed' ? 'success' : sale.status === 'annulled' ? 'danger' : 'warning'}">
                                    ${sale.status === 'completed' ? 'Entregado' : sale.status === 'annulled' ? 'Anulado' : 'Pendiente'}
                                </span>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
        
        // Display totals
        const completedCount = sales.filter(s => s.status === 'completed').length;
        const annulledCount = sales.filter(s => s.status === 'annulled').length;
        
        document.getElementById('history-sales-count').textContent = completedCount;
        document.getElementById('history-sales-annulled').textContent = annulledCount;
        document.getElementById('history-sales-total').textContent = totalAmount.toFixed(2);
        totalsDiv.style.display = 'block';
        
    } catch (error) {
        console.error('Error loading sales history:', error);
        const container = document.getElementById('sales-history-list');
        container.innerHTML = '<p class="text-muted">Error al cargar el historial de ventas</p>';
    }
}

function showSalesModule() {
    // Hide all sections first
    document.getElementById('new-sale-section').style.display = 'none';
    document.getElementById('current-sale-section').style.display = 'none';
    document.getElementById('pending-sales-section').style.display = 'none';
    
    // Check cash box status
    checkCashBoxStatusForSales();
    
    // Show current cash box sales if there's an open cash box
    const cashBoxWarning = document.getElementById('cash-box-closed-warning');
    if (cashBoxWarning.style.display === 'none') {
        document.getElementById('current-cashbox-sales-section').style.display = 'block';
        loadCurrentCashBoxSales();
    } else {
        document.getElementById('current-cashbox-sales-section').style.display = 'none';
    }
    
    // Always show sales history
    document.getElementById('sales-history-section').style.display = 'block';
    if (!document.getElementById('sales-year-filter').value) {
        initializeSalesHistory();
    }
}


