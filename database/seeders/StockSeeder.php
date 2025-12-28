<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockSeeder extends Seeder
{
    public function run(): void
    {
        // Get all products
        $products = DB::table('products')->get();

        foreach ($products as $product) {
            // Add initial stock for each product
            DB::table('stock_batches')->insert([
                'product_id' => $product->id,
                'batch_number' => 'LOTE-' . str_pad($product->id, 4, '0', STR_PAD_LEFT),
                'expiration_date' => now()->addYears(2),
                'quantity_initial' => 100, // 100 unidades base iniciales
                'quantity_available' => 100, // 100 unidades disponibles
                'location' => 'Bodega Principal',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        echo "Stock inicial agregado para todos los productos (100 unidades cada uno)\n";
    }
}
