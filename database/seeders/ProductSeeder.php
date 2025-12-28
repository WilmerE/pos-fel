<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'barcode' => 'CC001',
                'name' => 'Coca Cola',
                'description' => 'Bebida gaseosa',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'barcode' => 'AG001',
                'name' => 'Agua Pura',
                'description' => 'Agua embotellada',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'barcode' => 'GL001',
                'name' => 'Galletas Dulces',
                'description' => 'Galletas surtidas',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'barcode' => 'PN001',
                'name' => 'Pan Blanco',
                'description' => 'Pan de caja blanco',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'barcode' => 'LC001',
                'name' => 'Leche',
                'description' => 'Leche entera pasteurizada',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($products as $product) {
            $productId = DB::table('products')->insertGetId($product);

            // Add presentations for each product
            DB::table('product_presentations')->insert([
                [
                    'product_id' => $productId,
                    'name' => 'Unidad',
                    'factor' => 1,
                    'price' => 5.00,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'product_id' => $productId,
                    'name' => 'Caja (12 unidades)',
                    'factor' => 12,
                    'price' => 55.00,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        echo "Products and presentations seeded successfully!\n";
        echo "Created 5 products with 2 presentations each (Unidad and Caja)\n";
    }
}
