<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'Distribuidora Central S.A.',
                'contact_name' => 'Juan Pérez',
                'phone' => '2234-5678',
                'email' => 'ventas@districentral.com',
                'address' => 'Zona 12, Ciudad de Guatemala',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Alimentos del Norte',
                'contact_name' => 'María González',
                'phone' => '2345-6789',
                'email' => 'info@alimentosnorte.com',
                'address' => 'Km 22.5 Carretera al Atlántico',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bebidas Nacionales',
                'contact_name' => 'Carlos Ramírez',
                'phone' => '2456-7890',
                'email' => 'contacto@bebidasnac.com',
                'address' => 'Zona 4 de Mixco',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('suppliers')->insert($suppliers);

        echo "3 proveedores creados exitosamente!\n";
    }
}
