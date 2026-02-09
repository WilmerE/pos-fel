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
                'description' => 'Bebida gaseosa carbonatada',
                'category_id' => 1, // Bebidas
                'brand' => 'Coca-Cola',
                'location' => 'Anaquel A-1',
                'supplier_id' => 3, // Bebidas Nacionales
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'barcode' => 'AG001',
                'name' => 'Agua Pura Salvavidas',
                'description' => 'Agua embotellada purificada',
                'category_id' => 1, // Bebidas
                'brand' => 'Salvavidas',
                'location' => 'Anaquel A-2',
                'supplier_id' => 3, // Bebidas Nacionales
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'barcode' => 'GL001',
                'name' => 'Galletas María',
                'description' => 'Galletas dulces tipo María',
                'category_id' => 3, // Galletas
                'brand' => 'Dorán',
                'location' => 'Anaquel B-3',
                'supplier_id' => 2, // Alimentos del Norte
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'barcode' => 'PN001',
                'name' => 'Pan Blanco Bimbo',
                'description' => 'Pan de caja blanco grande',
                'category_id' => 5, // Panadería
                'brand' => 'Bimbo',
                'location' => 'Anaquel C-1',
                'supplier_id' => 2, // Alimentos del Norte
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'barcode' => 'LC001',
                'name' => 'Leche Foremost',
                'description' => 'Leche entera pasteurizada 1 litro',
                'category_id' => 4, // Lácteos
                'brand' => 'Foremost',
                'location' => 'Refrigerador A',
                'supplier_id' => 1, // Distribuidora Central
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($products as $product) {
            $productId = DB::table('products')->insertGetId($product);

            // Add presentations for each product with realistic prices
            DB::table('product_presentations')->insert([
                [
                    'product_id' => $productId,
                    'name' => 'Unidad',
                    'factor' => 1,
                    'purchase_price' => 4.00,
                    'sale_price' => 5.50,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'product_id' => $productId,
                    'name' => 'Caja (12 unidades)',
                    'factor' => 12,
                    'purchase_price' => 45.00,
                    'sale_price' => 60.00,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        echo "5 productos creados con marca, ubicación y proveedor!\n";
        echo "Cada producto tiene 2 presentaciones con precios de compra y venta\n";
    }
}
