<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashBoxController;
use App\Http\Controllers\Api\FiscalController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockController;
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

    // Sales
    Route::prefix('sales')->group(function () {
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
        Route::get('/presentations/{presentationId}', [ProductController::class, 'getPresentation']);
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
