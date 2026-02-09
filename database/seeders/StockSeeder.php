<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockSeeder extends Seeder
{
    public function run(): void
    {
        // Get all products with their presentations
        $products = DB::table('products')->get();

        foreach ($products as $product) {
            // Get first presentation (Unidad) for this product
            $presentation = DB::table('product_presentations')
                ->where('product_id', $product->id)
                ->where('name', 'Unidad')
                ->first();

            if ($presentation) {
                // Add initial stock batch linked to the presentation
                DB::table('stock_batches')->insert([
                    'product_id' => $product->id,
                    'presentation_id' => $presentation->id,
                    'batch_number' => 'LOTE-' . str_pad($product->id, 4, '0', STR_PAD_LEFT),
                    'expiration_date' => now()->addYears(2),
                    'quantity_initial' => 100,
                    'quantity_available' => 100,
                    'location' => 'Bodega Principal',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        echo "Stock inicial agregado para todos los productos (100 unidades cada uno)\n";
        echo "Cada lote está vinculado a la presentación 'Unidad'\n";
    }
}
