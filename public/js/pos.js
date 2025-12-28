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
            break;
        case 'stock':
            break;
        case 'fiscal':
            loadRecentFiscalDocuments();
            break;
    }
}

// Dashboard Module
async function loadDashboard() {
    try {
        // Load cash box status
        const cashBox = await apiRequest('/cash-box/summary');
        document.getElementById('cash-status').textContent = cashBox.data.status === 'open' ? 'Abierta' : 'Cerrada';
        document.getElementById('cash-total').textContent = `Q ${parseFloat(cashBox.data.current_cash || 0).toFixed(2)}`;

        // Load pending sales (would need endpoint)
        document.getElementById('pending-sales').textContent = '0';
        document.getElementById('fiscal-count').textContent = '0';
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
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
function showNewSaleForm() {
    document.getElementById('new-sale-section').style.display = 'block';
    document.getElementById('current-sale-section').style.display = 'none';
    document.getElementById('pending-sales-section').style.display = 'none';
}

async function loadPendingSales() {
    document.getElementById('new-sale-section').style.display = 'none';
    document.getElementById('current-sale-section').style.display = 'none';
    document.getElementById('pending-sales-section').style.display = 'block';

    try {
        const response = await apiRequest('/sales/pending');
        const sales = response.data;

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
    } catch (error) {
        console.error('Error loading pending sales:', error);
    }
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
        
        // Clear current sale and show new sale form after short delay
        setTimeout(() => {
            state.currentSale = null;
            showNewSaleForm();
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
        showNewSaleForm();
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
    
    // Populate presentations dropdown
    const select = document.getElementById('presentation-select');
    select.innerHTML = '<option value="">Seleccione presentación</option>' + 
        product.presentations.map(p => 
            `<option value="${p.id}" data-price="${p.price}">${p.name} - Q ${parseFloat(p.price).toFixed(2)}</option>`
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
}

function showCheckStockForm() {
    document.getElementById('add-stock-section').style.display = 'none';
    document.getElementById('check-stock-section').style.display = 'block';
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
            showToast('Stock agregado correctamente', 'success');
            e.target.reset();
        } catch (error) {
            // Error shown
        }
    });

    // Check stock form
    document.getElementById('check-stock-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const productId = e.target.product_id.value;

        try {
            const response = await apiRequest(`/stock/check/${productId}`);
            const stock = response.data;
            
            document.getElementById('stock-result').innerHTML = `
                <div class="alert success">
                    <strong>Stock Disponible: ${stock.available_quantity} unidades</strong><br>
                    Producto ID: ${productId}
                </div>
            `;
        } catch (error) {
            document.getElementById('stock-result').innerHTML = `
                <div class="alert error">
                    <strong>Error al consultar stock</strong><br>
                    ${error.message}
                </div>
            `;
        }
    });

    // View batches form
    document.getElementById('view-batches-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const productId = e.target.product_id.value;

        try {
            const response = await apiRequest(`/stock/batches/${productId}`);
            const batches = response.data;

            const container = document.getElementById('stock-batches-list');
            
            if (batches.length === 0) {
                container.innerHTML = '<p class="text-muted">No hay lotes disponibles para este producto</p>';
                return;
            }

            container.innerHTML = `
                <table class="table">
                    <thead>
                        <tr>
                            <th>Lote</th>
                            <th>Cantidad Disponible</th>
                            <th>Fecha Vencimiento</th>
                            <th>Ubicación</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${batches.map(batch => `
                            <tr>
                                <td>${batch.batch_number || 'N/A'}</td>
                                <td>${batch.quantity_available} / ${batch.quantity_initial}</td>
                                <td>${batch.expiration_date ? new Date(batch.expiration_date).toLocaleDateString() : 'N/A'}</td>
                                <td>${batch.location || 'N/A'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        } catch (error) {
            document.getElementById('stock-batches-list').innerHTML = `
                <p class="text-muted">Error al cargar lotes</p>
            `;
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
});
