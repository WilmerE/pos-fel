<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Stock permissions
            ['name' => 'manage_stock', 'description' => 'Agregar y gestionar stock de productos'],
            ['name' => 'adjust_stock', 'description' => 'Realizar ajustes de inventario'],
            ['name' => 'view_stock', 'description' => 'Consultar inventario y stock'],

            // Sales permissions
            ['name' => 'sell', 'description' => 'Crear y confirmar ventas'],
            ['name' => 'cancel_sale', 'description' => 'Cancelar ventas no facturadas'],
            ['name' => 'view_sales', 'description' => 'Consultar ventas'],

            // Annulment permissions
            ['name' => 'annul_sale', 'description' => 'Anular ventas facturadas'],
            ['name' => 'approve_annulment', 'description' => 'Aprobar solicitudes de anulación'],

            // FEL permissions
            ['name' => 'generate_invoice', 'description' => 'Generar facturas electrónicas'],
            ['name' => 'view_fiscal_documents', 'description' => 'Consultar documentos fiscales'],

            // Cash box permissions
            ['name' => 'open_cash_box', 'description' => 'Abrir caja'],
            ['name' => 'close_cash_box', 'description' => 'Cerrar caja'],
            ['name' => 'register_expense', 'description' => 'Registrar egresos en caja'],
            ['name' => 'manage_cash_movements', 'description' => 'Gestionar movimientos de caja'],
            ['name' => 'view_cash_box', 'description' => 'Ver estado de caja'],

            // Product permissions
            ['name' => 'manage_products', 'description' => 'Crear, editar y gestionar productos'],
            ['name' => 'view_products', 'description' => 'Consultar productos'],

            // User management permissions
            ['name' => 'manage_users', 'description' => 'Gestionar usuarios del sistema'],
            ['name' => 'manage_roles', 'description' => 'Gestionar roles y permisos'],

            // Reports permissions
            ['name' => 'view_reports', 'description' => 'Ver reportes del sistema'],
            ['name' => 'export_data', 'description' => 'Exportar datos y reportes'],
        ];

        foreach ($permissions as $permissionData) {
            Permission::updateOrCreate(
                ['name' => $permissionData['name']],
                ['description' => $permissionData['description']]
            );
        }

        $this->command->info('Permissions seeded successfully!');
    }
}
