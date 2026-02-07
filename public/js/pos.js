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
async function apiRequest(endpoint, options = {}) {
    showLoading();
    try {
        const config = {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(state.token ? { 'Authorization': `Bearer ${state.token}` } : {}),
                ...options.headers
            }
        };

        const response = await fetch(`${state.apiUrl}${endpoint}`, config);
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

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span>${message}</span>
    `;
    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 4000);
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
    loadModuleData('dashboard');
}

// Module Data Loading
async function loadModuleData(moduleName) {
    switch (moduleName) {
        case 'dashboard':
            loadDashboard();
            break;
        case 'cash-box':
            loadCashBoxSummary();
            break;
        case 'sales':
            initSalesModule();
            break;
        case 'stock':
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

// Cash Box Module
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

// Sales Module
async function initSalesModule() {
    // Hide all sections first
    document.getElementById('new-sale-section').style.display = 'none';
    document.getElementById('current-sale-section').style.display = 'none';
    document.getElementById('pending-sales-section').style.display = 'none';
    
    // Show buttons initially
    document.getElementById('sales-header-actions').style.display = 'flex';
    
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
function showAddStockForm() {
    document.getElementById('add-stock-section').style.display = 'block';
    document.getElementById('check-stock-section').style.display = 'none';
    // Limpiar resultados previos
    document.getElementById('stock-result').innerHTML = '';
    document.getElementById('stock-batches-list').innerHTML = '';
}

function showCheckStockForm() {
    document.getElementById('add-stock-section').style.display = 'none';
    document.getElementById('check-stock-section').style.display = 'block';
    // Limpiar resultados previos
    document.getElementById('stock-result').innerHTML = '';
    document.getElementById('stock-batches-list').innerHTML = '';
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

    // Load product categories for catalog filter
    loadProductCategories();

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
                searchResults.innerHTML = products.map((product, index) => `
                    <div class="search-result-item" data-index="${index}">
                        <div class="product-name">${product.name}</div>
                        <div class="product-info">
                            <span class="product-barcode">${product.barcode}</span>
                            ${product.description || ''}
                        </div>
                    </div>
                `).join('');
                
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

        try {
            await apiRequest('/stock/add', {
                method: 'POST',
                body: JSON.stringify({
                    product_id: parseInt(data.product_id),
                    quantity: parseInt(data.quantity),
                    unit_cost: parseFloat(data.unit_cost),
                    expiration_date: data.expiration_date || null,
                    location: data.location || null,
                    batch_number: data.batch_number || null
                })
            });
            showToast('✅ Stock agregado correctamente', 'success');
            e.target.reset();
        } catch (error) {
            // Error shown by apiRequest
        }
    });

    // Check stock form
    document.getElementById('check-stock-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const productId = e.target.product_id.value;

        try {
            // Consultar disponibilidad
            const response = await apiRequest(`/stock/check/${productId}`);
            const stock = response.data;
            
            document.getElementById('stock-result').innerHTML = `
                <div class="alert success">
                    <strong>📦 Stock Disponible: ${stock.available_quantity} unidades</strong><br>
                    Producto ID: ${productId}
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
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Lote</th>
                                <th>Cantidad Disponible</th>
                                <th>Fecha Vencimiento</th>
                                <th>Ubicación</th>
                                <th>Costo Unit.</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${batches.map(batch => `
                                <tr>
                                    <td>${batch.batch_number || 'N/A'}</td>
                                    <td><strong>${batch.quantity_available}</strong> / ${batch.quantity_initial}</td>
                                    <td>${batch.expiration_date ? new Date(batch.expiration_date).toLocaleDateString('es-GT') : 'N/A'}</td>
                                    <td>${batch.location || 'N/A'}</td>
                                    <td>Q ${parseFloat(batch.unit_cost || 0).toFixed(2)}</td>
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
    });

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
    document.getElementById('product-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);

        // Remove empty category_id
        if (!data.category_id) {
            delete data.category_id;
        }

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
                const response = await apiRequest('/products', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });
                showToast('Producto creado exitosamente', 'success');
                
                // Switch to edit mode to allow adding presentations
                currentEditingProductId = response.data.id;
                editProduct(response.data.id);
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
        
        return `
            <div class="product-card">
                <div class="product-card-header">
                    <div style="flex: 1;">
                        <div class="product-card-title">${product.name}</div>
                        <div class="product-card-barcode">${product.barcode}</div>
                        <div style="margin-top: 4px;">${categoryBadge}</div>
                    </div>
                </div>
                <div class="product-card-price">Q ${formatNumber(product.price_with_iva)}</div>
                <div style="font-size: 12px; color: #64748b; margin-bottom: 8px;">
                    Base: Q ${formatNumber(product.base_price)} + IVA
                </div>
                <div class="product-card-stock">
                    <span>Stock: ${product.total_stock} unidades</span>
                    <span class="stock-badge ${stockClass}">${stockClass === 'high' ? 'Alto' : stockClass === 'medium' ? 'Medio' : 'Bajo'}</span>
                </div>
                <div style="font-size: 12px; color: #64748b; margin-top: 8px; padding-top: 8px; border-top: 1px solid #f1f5f9;">
                    📍 ${locations}
                </div>
                <button class="btn btn-sm btn-primary" onclick="editProduct(${product.id})" style="width: 100%; margin-top: 12px;">
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
    
    // Toggle button
    document.getElementById('new-product-btn').style.display = 'none';
}

function cancelProductForm() {
    currentEditingProductId = null;
    document.getElementById('product-form-section').style.display = 'none';
    document.getElementById('catalog-view-section').style.display = 'block';
    
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
                    <th>Precio (sin IVA)</th>
                    <th>Precio (con IVA)</th>
                    <th>Unidades</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                ${presentations.map(p => `
                    <tr>
                        <td>${p.name}</td>
                        <td>Q ${formatNumber(p.price)}</td>
                        <td>Q ${formatNumber(p.price * 1.12)}</td>
                        <td>${p.factor || 1}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editPresentation(${p.id}, '${p.name}', ${p.price}, ${p.factor || 1})" style="margin-right: 5px;">Editar</button>
                            <button class="btn btn-sm btn-danger" onclick="deletePresentation(${p.id})">Eliminar</button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function editPresentation(presentationId, name, price, factor) {
    currentEditingPresentationId = presentationId;
    
    // Update form title and button
    document.getElementById('presentation-form-title').textContent = 'Editar Presentación';
    document.getElementById('save-presentation-btn').textContent = 'Actualizar Presentación';
    document.getElementById('cancel-presentation-btn').style.display = 'inline-block';
    
    // Fill form
    document.getElementById('presentation-id').value = presentationId;
    document.getElementById('presentation-name').value = name;
    document.getElementById('presentation-price').value = price;
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
