<?php

namespace Database\Seeders;

use App\Models\CashBox;
use App\Models\CashMovement;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    private array $productCategories = [
        'Bebidas' => ['Coca Cola', 'Pepsi', 'Fanta', 'Sprite', 'Agua Pura', 'Té Helado', 'Jugo Naranja', 'Jugo Manzana', 'Red Bull', 'Gatorade'],
        'Snacks' => ['Doritos', 'Cheetos', 'Pringles', 'Papas Lays', 'Takis', 'Churro', 'Palomitas', 'Snickers', 'Kit Kat', 'M&Ms'],
        'Galletas' => ['Oreo', 'Chips Ahoy', 'Galleta María', 'Galleta Ritz', 'Club Social', 'Choco Krispis', 'Zucaritas', 'Corn Flakes'],
        'Lácteos' => ['Leche Trebol', 'Yogurt Yoplait', 'Queso Crema', 'Mantequilla', 'Queso Mozzarella', 'Yogurt Griego'],
        'Panadería' => ['Pan Blanco', 'Pan Integral', 'Pan Dulce', 'Croissant', 'Donas', 'Muffin Chocolate'],
        'Higiene' => ['Jabón Protex', 'Shampoo Pantene', 'Pasta Colgate', 'Papel Higiénico', 'Desodorante', 'Toallas Femeninas'],
    ];

    private array $customerNames = [
        'Juan Carlos López', 'María Fernanda García', 'Pedro Antonio Rodríguez', 'Ana Lucía Martínez',
        'Carlos Eduardo Pérez', 'Sofía Isabel Gómez', 'Luis Fernando Hernández', 'Carmen Rosa Díaz',
        'Jorge Alberto Morales', 'Patricia Elena Cruz', 'Roberto Miguel Flores', 'Laura Beatriz Ramos',
        'Andrés Felipe Santos', 'Diana Carolina Torres', 'Manuel Alejandro Vargas', 'Claudia Esperanza Ruiz',
        'Fernando José Castillo', 'Mónica Alejandra Jiménez', 'Ricardo Javier Méndez', 'Gabriela Victoria Ortiz'
    ];

    public function run(): void
    {
        DB::beginTransaction();

        try {
            echo "🚀 Iniciando generación de datos de demostración...\n\n";

            // 1. Crear más productos (50 productos totales)
            echo "📦 Creando productos...\n";
            $products = $this->createProducts();
            echo "   ✓ {$products->count()} productos creados\n\n";

            // 2. Generar nombres de clientes para usar en ventas
            echo "👥 Preparando clientes...\n";
            $customers = $this->customerNames;
            echo "   ✓ " . count($customers) . " nombres de clientes preparados\n\n";

            // 3. Obtener usuarios para las operaciones
            $users = User::all();
            $cashier = $users->firstWhere('email', 'cashier@pos.com');
            $admin = $users->firstWhere('email', 'admin@pos.com');
            $warehouse = $users->firstWhere('email', 'warehouse@pos.com');

            // 4. Crear primero todo el inventario (500+ lotes)
            echo "🏭 Creando lotes de inventario...\n";
            $batches = $this->createStockBatches($products, $warehouse);
            echo "   ✓ {$batches->count()} lotes de inventario creados\n\n";

            // 5. Crear 3 ciclos de caja con ventas
            echo "💰 Creando ciclos de caja con ventas...\n";
            $this->createCashCyclesWithSales($cashier, $admin, $products, $customers);
            echo "   ✓ 3 ciclos de caja completados\n\n";

            DB::commit();

            echo "✅ Datos de demostración generados exitosamente!\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "📊 Resumen:\n";
            echo "   • Productos: " . Product::count() . "\n";
            echo "   • Lotes de inventario: " . StockBatch::count() . "\n";
            echo "   • Movimientos de stock: " . StockMovement::count() . "\n";
            echo "   • Aperturas de caja: " . CashBox::whereNotNull('closed_at')->count() . "\n";
            echo "   • Ventas: " . Sale::count() . "\n";
            echo "   • Detalles de venta: " . SaleItem::count() . "\n";
            echo "   • Movimientos de caja: " . CashMovement::count() . "\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        } catch (\Exception $e) {
            DB::rollBack();
            echo "❌ Error: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    private function createProducts()
    {
        $products = collect();
        $productId = Product::max('id') ?? 0;

        foreach ($this->productCategories as $category => $items) {
            foreach ($items as $index => $name) {
                $productId++;
                $barcode = 'PRD' . str_pad($productId, 5, '0', STR_PAD_LEFT);

                $product = Product::create([
                    'name' => $name,
                    'description' => "Producto de {$category} - {$name}",
                    'barcode' => $barcode,
                    'category_id' => null,
                    'active' => 1,
                ]);

                // Crear 2-3 presentaciones por producto
                $presentations = [
                    ['name' => 'Unidad', 'multiplier' => 1, 'price' => rand(5, 15)],
                    ['name' => 'Pack x6', 'multiplier' => 6, 'price' => rand(25, 75)],
                ];

                if (rand(0, 1)) {
                    $presentations[] = ['name' => 'Caja x24', 'multiplier' => 24, 'price' => rand(100, 250)];
                }

                foreach ($presentations as $pres) {
                    ProductPresentation::create([
                        'product_id' => $product->id,
                        'name' => $pres['name'],
                        'factor' => $pres['multiplier'],
                        'price' => $pres['price'] + (rand(0, 50) / 10),
                    ]);
                }

                $products->push($product);
            }
        }

        return $products;
    }

    private function createStockBatches($products, $warehouse)
    {
        $batches = collect();
        $baseDate = now()->subDays(90); // Comenzar hace 3 meses

        foreach ($products as $product) {
            // Cada producto recibe entre 8-12 lotes de inventario
            $numBatches = rand(8, 12);

            for ($i = 0; $i < $numBatches; $i++) {
                $quantity = rand(50, 200);
                $batchDate = $baseDate->copy()->addDays(rand(0, 85));

                $batch = StockBatch::create([
                    'product_id' => $product->id,
                    'batch_number' => 'LOTE-' . $product->id . '-' . ($i + 1) . '-' . $batchDate->format('Ymd'),
                    'quantity_initial' => $quantity,
                    'quantity_available' => $quantity,
                    'expiration_date' => $batchDate->copy()->addMonths(rand(6, 24)),
                    'location' => 'Bodega ' . chr(65 + rand(0, 3)) . ', Anaquel ' . rand(1, 20),
                    'created_at' => $batchDate,
                    'updated_at' => $batchDate,
                ]);

                // Crear movimiento de entrada
                StockMovement::create([
                    'product_id' => $product->id,
                    'stock_batch_id' => $batch->id,
                    'type' => 'in',
                    'quantity' => $quantity,
                    'user_id' => $warehouse->id,
                    'reference_type' => 'purchase',
                    'reference_id' => null,
                    'notes' => 'Compra inicial de inventario',
                    'created_at' => $batchDate,
                    'updated_at' => $batchDate,
                ]);

                $batches->push($batch);
            }
        }

        return $batches;
    }

    private function createCashCyclesWithSales($cashier, $admin, $products, $customers)
    {
        $baseDate = now()->subDays(30);
        $presentationsByProduct = ProductPresentation::all()->groupBy('product_id');

        for ($cycle = 1; $cycle <= 3; $cycle++) {
            $cycleStartDate = $baseDate->copy()->addDays(($cycle - 1) * 10);
            
            echo "   🔄 Ciclo {$cycle}: " . $cycleStartDate->format('Y-m-d') . "\n";

            // Abrir caja
            $opening = CashBox::create([
                'opened_by' => $cashier->id,
                'opening_amount' => $cycle === 1 ? 500.00 : rand(300, 700),
                'opened_at' => $cycleStartDate,
                'closed_at' => null,
            ]);

            $currentDate = $cycleStartDate->copy();
            $totalSales = rand(50, 80);
            $salesCreated = 0;

            // Crear ventas distribuidas en varios días
            for ($day = 0; $day < 8; $day++) {
                $dailySales = rand(5, 12);

                for ($s = 0; $s < $dailySales && $salesCreated < $totalSales; $s++) {
                    $saleDate = $currentDate->copy()->addHours(rand(8, 20))->addMinutes(rand(0, 59));
                    $customerName = $customers[array_rand($customers)];
                    $customerNit = rand(0, 3) === 0 ? 'CF' : rand(10000000, 99999999) . '-' . rand(0, 9);

                    // Crear venta
                    $sale = Sale::create([
                        'user_id' => $cashier->id,
                        'cashier_id' => $cashier->id,
                        'customer_name' => $customerName,
                        'customer_nit' => $customerNit,
                        'subtotal' => 0,
                        'tax' => 0,
                        'total' => 0,
                        'status' => 'completed',
                        'created_at' => $saleDate,
                        'updated_at' => $saleDate,
                    ]);

                    // Agregar productos aleatorios a la venta
                    $numItems = rand(1, 6);
                    $saleSubtotal = 0;

                    for ($item = 0; $item < $numItems; $item++) {
                        $product = $products->random();
                        $presentations = $presentationsByProduct[$product->id] ?? collect();
                        
                        if ($presentations->isEmpty()) continue;

                        $presentation = $presentations->random();
                        $quantity = rand(1, 5);
                        $unitPrice = $presentation->price;
                        $subtotal = $quantity * $unitPrice;

                        // Reducir stock usando FIFO
                        $this->reduceStock($product->id, $quantity, $sale->id, $cashier->id, $saleDate);

                        SaleItem::create([
                            'sale_id' => $sale->id,
                            'product_id' => $product->id,
                            'presentation_id' => $presentation->id,
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'total' => $subtotal,
                        ]);

                        $saleSubtotal += $subtotal;
                    }

                    // Actualizar totales de venta
                    $tax = $saleSubtotal * 0.12;
                    $total = $saleSubtotal + $tax;

                    $sale->update([
                        'subtotal' => $saleSubtotal,
                        'tax' => $tax,
                        'total' => $total,
                    ]);

                    $salesCreated++;
                }

                // Agregar algunos movimientos de caja aleatorios
                if (rand(0, 2) === 0) {
                    $movementDate = $currentDate->copy()->addHours(rand(8, 18));
                    $type = rand(0, 1) ? 'income' : 'expense';
                    
                    CashMovement::create([
                        'cash_box_id' => $opening->id,
                        'user_id' => $cashier->id,
                        'sale_id' => null,
                        'type' => $type,
                        'amount' => rand(50, 300) + (rand(0, 99) / 100),
                        'description' => $type === 'income' ? 
                            'Ingreso por ' . ['servicio extra', 'propina', 'ajuste positivo'][rand(0, 2)] :
                            'Egreso por ' . ['compra de insumos', 'pago de servicios', 'gastos varios'][rand(0, 2)],
                        'created_at' => $movementDate,
                    ]);
                }

                $currentDate->addDay();
            }

            // Cerrar caja
            $closingDate = $currentDate->copy()->setTime(20, 0);
            $totalIncome = Sale::whereBetween('created_at', [$cycleStartDate, $closingDate])
                ->where('cashier_id', $cashier->id)
                ->sum('total');
            $movements = CashMovement::where('cash_box_id', $opening->id)->get();
            $incomeMovements = $movements->where('type', 'income')->sum('amount');
            $expenseMovements = $movements->where('type', 'expense')->sum('amount');
            
            $closingAmount = $opening->opening_amount + $totalIncome + $incomeMovements - $expenseMovements;

            $opening->update([
                'closed_at' => $closingDate,
                'closed_by' => $admin->id,
                'closing_amount' => $closingAmount,
                'updated_at' => $closingDate,
            ]);

            echo "      ✓ {$salesCreated} ventas creadas\n";
            echo "      ✓ Total en ventas: Q " . number_format($totalIncome, 2) . "\n";
        }
    }

    private function reduceStock($productId, $quantity, $saleId, $userId, $date)
    {
        $batches = StockBatch::where('product_id', $productId)
            ->where('quantity_available', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;

            $toReduce = min($remaining, $batch->quantity_available);
            
            $batch->quantity_available -= $toReduce;
            $batch->save();

            StockMovement::create([
                'product_id' => $productId,
                'stock_batch_id' => $batch->id,
                'type' => 'out',
                'quantity' => $toReduce,
                'user_id' => $userId,
                'reference_type' => 'sale',
                'reference_id' => $saleId,
                'notes' => 'Venta',
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            $remaining -= $toReduce;
        }
    }
}
