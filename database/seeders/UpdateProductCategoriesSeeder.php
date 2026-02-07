<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class UpdateProductCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Get categories
        $bebidas = Category::where('name', 'Bebidas')->first();
        $snacks = Category::where('name', 'Snacks')->first();
        $galletas = Category::where('name', 'Galletas')->first();
        $lacteos = Category::where('name', 'Lácteos')->first();
        $panaderia = Category::where('name', 'Panadería')->first();
        $higiene = Category::where('name', 'Higiene')->first();

        // Mapping: product name keywords => category
        $categoryMappings = [
            'coca cola' => $bebidas,
            'pepsi' => $bebidas,
            'agua' => $bebidas,
            'jugo' => $bebidas,
            'sprite' => $bebidas,
            'fanta' => $bebidas,
            'refresco' => $bebidas,
            
            'papas' => $snacks,
            'doritos' => $snacks,
            'cheetos' => $snacks,
            'pringles' => $snacks,
            'nachos' => $snacks,
            
            'oreo' => $galletas,
            'galleta' => $galletas,
            'ritz' => $galletas,
            'marías' => $galletas,
            'chips ahoy' => $galletas,
            
            'leche' => $lacteos,
            'yogurt' => $lacteos,
            'queso' => $lacteos,
            
            'pan' => $panaderia,
            'barra' => $panaderia,
            'francés' => $panaderia,
            'dulce' => $panaderia,
            
            'jabón' => $higiene,
            'shampoo' => $higiene,
            'pasta dental' => $higiene,
            'cepillo' => $higiene,
        ];

        $products = Product::all();
        $updated = 0;
        $skipped = 0;

        foreach ($products as $product) {
            $productNameLower = strtolower($product->name);
            $assigned = false;

            // Try to match product name with category keywords
            foreach ($categoryMappings as $keyword => $category) {
                if (stripos($productNameLower, $keyword) !== false) {
                    $product->category_id = $category->id;
                    $product->save();
                    $assigned = true;
                    $updated++;
                    break;
                }
            }

            // Intentionally leave some products without category (10% probability)
            if (!$assigned && rand(1, 10) > 2) {
                // Assign a random category to unmatched products (except 10%)
                $randomCategory = collect([$bebidas, $snacks, $galletas, $lacteos, $panaderia, $higiene])->random();
                $product->category_id = $randomCategory->id;
                $product->save();
                $updated++;
            } else if (!$assigned) {
                // Leave without category
                $skipped++;
            }
        }

        $this->command->info("✓ {$updated} productos actualizados con categoría");
        $this->command->info("✓ {$skipped} productos dejados SIN categoría (para pruebas)");
    }
}
