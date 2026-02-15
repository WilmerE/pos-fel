<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashBoxController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FiscalController;
use App\Http\Controllers\Api\InventoryImportController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\SupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);

    // Sales
    Route::prefix('sales')->group(function () {
        Route::get('/', [SaleController::class, 'index']); // List all sales with filters
        Route::get('/current-cash-box', [SaleController::class, 'currentCashBox']); // Sales from current open cash box
        Route::post('/', [SaleController::class, 'create']);
        Route::get('/pending', [SaleController::class, 'pending']);
        Route::get('/{saleId}', [SaleController::class, 'show']);
        Route::post('/{saleId}/items', [SaleController::class, 'addItem']);
        Route::put('/items/{saleItemId}', [SaleController::class, 'updateItem']);
        Route::delete('/items/{saleItemId}', [SaleController::class, 'removeItem']);
        Route::post('/{saleId}/confirm', [SaleController::class, 'confirm']);
        Route::post('/{saleId}/cancel', [SaleController::class, 'cancel']);
    });

    // Products
    Route::prefix('products')->group(function () {
        Route::get('/search', [ProductController::class, 'search']);
        Route::get('/catalog', [ProductController::class, 'catalog']);
        Route::get('/categories', [ProductController::class, 'categories']);
        Route::get('/expiration-notifications', [ProductController::class, 'expirationNotifications']);
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/{productId}', [ProductController::class, 'show']);
        Route::put('/{productId}', [ProductController::class, 'update']);
        Route::post('/{productId}/presentations', [ProductController::class, 'addPresentation']);
        Route::put('/presentations/{presentationId}', [ProductController::class, 'updatePresentation']);
        Route::delete('/presentations/{presentationId}', [ProductController::class, 'deletePresentation']);
        Route::get('/presentations/{presentationId}', [ProductController::class, 'getPresentation']);
    });

    // Categories
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{categoryId}', [CategoryController::class, 'update']);
        Route::delete('/{categoryId}', [CategoryController::class, 'destroy']);
    });

    // Suppliers
    Route::prefix('suppliers')->group(function () {
        Route::get('/', [SupplierController::class, 'index']);
        Route::post('/', [SupplierController::class, 'store']);
        Route::get('/{supplierId}', [SupplierController::class, 'show']);
        Route::put('/{supplierId}', [SupplierController::class, 'update']);
        Route::delete('/{supplierId}', [SupplierController::class, 'destroy']);
    });

    // Inventory Import
    Route::prefix('inventory/import')->group(function () {
        Route::get('/history', [InventoryImportController::class, 'history']);
        Route::post('/preview', [InventoryImportController::class, 'preview']);
        Route::post('/preview-json', [InventoryImportController::class, 'previewJson']);
        Route::post('/preview-category', [InventoryImportController::class, 'previewCategory']);
        Route::post('/commit', [InventoryImportController::class, 'commit']);
    });

    // Stock
    Route::prefix('stock')->group(function () {
        Route::post('/add', [StockController::class, 'addStock']);
        Route::post('/adjust', [StockController::class, 'adjustStock']);
        Route::get('/available/{productId}', [StockController::class, 'getAvailableStock']);
        Route::get('/check/{productId}', [StockController::class, 'checkStock']);
        Route::get('/batches/{productId}', [StockController::class, 'getStockBatches']);
    });

    // Cash Box
    Route::prefix('cash-box')->group(function () {
        Route::get('/', [CashBoxController::class, 'index']);
        Route::get('/summary', [CashBoxController::class, 'getCashBoxSummary']);
        Route::get('/movements', [CashBoxController::class, 'getCashBoxMovements']);
        Route::post('/open', [CashBoxController::class, 'openCashBox']);
        Route::post('/close', [CashBoxController::class, 'closeCashBox']);
        Route::post('/income', [CashBoxController::class, 'registerIncome']);
        Route::post('/expense', [CashBoxController::class, 'registerExpense']);
    });

    // Fiscal / FEL
    Route::prefix('fiscal')->group(function () {
        Route::get('/documents', [FiscalController::class, 'index']);
        Route::get('/documents/{fiscalDocumentId}', [FiscalController::class, 'show']);
        Route::get('/sales/{saleId}/invoice-data', [FiscalController::class, 'generateInvoiceData']);
        Route::post('/sales/{saleId}/register', [FiscalController::class, 'registerFiscalDocument']);
        Route::post('/sales/{saleId}/annul', [FiscalController::class, 'annulSale']);
        Route::get('/sales/{saleId}/can-annul', [FiscalController::class, 'canAnnulSale']);
        Route::get('/annulments', [FiscalController::class, 'getAnnulments']);
        Route::get('/annulments/{annulmentId}', [FiscalController::class, 'getAnnulmentDetails']);
    });
});
