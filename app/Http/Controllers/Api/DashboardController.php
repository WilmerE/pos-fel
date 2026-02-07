<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashBox;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();
        
        try {
            // Ventas de hoy
            $today = now()->startOfDay();
            $salesToday = Sale::where('created_at', '>=', $today)
                ->where('status', 'completed')
                ->count();
            
            $totalToday = Sale::where('created_at', '>=', $today)
                ->where('status', 'completed')
                ->sum('total');

            // Ventas del mes
            $startOfMonth = now()->startOfMonth();
            $salesThisMonth = Sale::where('created_at', '>=', $startOfMonth)
                ->where('status', 'completed')
                ->count();
            
            $totalThisMonth = Sale::where('created_at', '>=', $startOfMonth)
                ->where('status', 'completed')
                ->sum('total');

            // Productos con bajo stock (menos de 20 unidades)
            $lowStock = StockBatch::select('product_id', DB::raw('SUM(quantity_available) as total_stock'))
                ->groupBy('product_id')
                ->havingRaw('SUM(quantity_available) < 20')
                ->count();

            // Productos más vendidos (últimos 30 días)
            $topProducts = SaleItem::select('product_id', 'products.name', DB::raw('SUM(sale_items.quantity) as total_sold'))
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->where('sales.created_at', '>=', now()->subDays(30))
                ->where('sales.status', 'completed')
                ->groupBy('product_id', 'products.name')
                ->orderByDesc('total_sold')
                ->limit(5)
                ->get();

            // Ventas por día (últimos 7 días)
            $salesByDay = Sale::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(total) as total')
                )
                ->where('created_at', '>=', now()->subDays(7))
                ->where('status', 'completed')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();

            // Historial de cajas (últimas 5)
            $cashBoxHistory = CashBox::select([
                    'id',
                    'opening_amount',
                    'closing_amount',
                    'opened_at',
                    'closed_at',
                    'opened_by',
                    'closed_by'
                ])
                ->whereNotNull('closed_at')
                ->with(['openedBy:id,name', 'closedBy:id,name'])
                ->orderBy('closed_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($box) {
                    // Calcular ventas de esta caja
                    $sales = Sale::whereBetween('created_at', [$box->opened_at, $box->closed_at])
                        ->where('status', 'completed')
                        ->sum('total');
                    
                    return [
                        'id' => $box->id,
                        'opening_amount' => $box->opening_amount,
                        'closing_amount' => $box->closing_amount,
                        'total_sales' => $sales,
                        'difference' => $box->closing_amount - $box->opening_amount,
                        'opened_at' => $box->opened_at,
                        'closed_at' => $box->closed_at,
                        'opened_by' => $box->openedBy->name ?? 'N/A',
                        'closed_by' => $box->closedBy->name ?? 'N/A',
                    ];
                });

            // Estado de caja actual
            $currentCashBox = CashBox::whereNull('closed_at')
                ->with('openedBy:id,name')
                ->orderBy('opened_at', 'desc')
                ->first();

            $cashBoxStatus = [
                'is_open' => $currentCashBox !== null,
                'opening_amount' => $currentCashBox->opening_amount ?? 0,
                'opened_at' => $currentCashBox->opened_at ?? null,
                'opened_by' => $currentCashBox->openedBy->name ?? null,
            ];

            if ($currentCashBox) {
                $currentSales = Sale::where('created_at', '>=', $currentCashBox->opened_at)
                    ->where('status', 'completed')
                    ->sum('total');
                
                $cashBoxStatus['current_sales'] = $currentSales;
                $cashBoxStatus['current_total'] = $currentCashBox->opening_amount + $currentSales;
            }

            return response()->json([
                'message' => 'Estadísticas obtenidas exitosamente.',
                'data' => [
                    'today' => [
                        'sales_count' => $salesToday,
                        'sales_total' => round($totalToday, 2),
                    ],
                    'month' => [
                        'sales_count' => $salesThisMonth,
                        'sales_total' => round($totalThisMonth, 2),
                    ],
                    'low_stock_count' => $lowStock,
                    'top_products' => $topProducts,
                    'sales_by_day' => $salesByDay,
                    'cash_box_history' => $cashBoxHistory,
                    'current_cash_box' => $cashBoxStatus,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener estadísticas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
