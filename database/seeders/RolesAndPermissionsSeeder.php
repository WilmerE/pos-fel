<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed permissions first
        $this->call(PermissionsSeeder::class);

        // Create Permissions reference (already seeded above)
        $permissions = [
            // Stock permissions
            [
                'name' => 'manage_stock',
                'description' => 'Agregar y gestionar stock de productos',
            ],
            [
                'name' => 'adjust_stock',
                'description' => 'Realizar ajustes de inventario',
            ],
            [
                'name' => 'view_stock',
                'description' => 'Consultar inventario y stock',
            ],

            // Sales permissions
            [
                'name' => 'sell',
                'description' => 'Crear y confirmar ventas',
            ],
            [
                'name' => 'cancel_sale',
                'description' => 'Cancelar ventas no facturadas',
            ],
            [
                'name' => 'view_sales',
                'description' => 'Consultar ventas',
            ],

            // Annulment permissions
            [
                'name' => 'annul_sale',
                'description' => 'Anular ventas facturadas',
            ],
            [
                'name' => 'approve_annulment',
                'description' => 'Aprobar solicitudes de anulación',
            ],

            // FEL permissions
            [
                'name' => 'generate_invoice',
                'description' => 'Generar facturas electrónicas',
            ],
            [
                'name' => 'view_fiscal_documents',
                'description' => 'Consultar documentos fiscales',
            ],

            // Cash box permissions
            [
                'name' => 'open_cash_box',
                'description' => 'Abrir caja',
            ],
            [
                'name' => 'close_cash_box',
                'description' => 'Cerrar caja',
            ],
            [
                'name' => 'register_expense',
                'description' => 'Registrar egresos en caja',
            ],
            [
                'name' => 'manage_cash_movements',
                'description' => 'Gestionar movimientos de caja',
            ],
            [
                'name' => 'view_cash_box',
                'description' => 'Ver estado de caja',
            ],

            // Product permissions
            [
                'name' => 'manage_products',
                'description' => 'Crear, editar y gestionar productos',
            ],
            [
                'name' => 'view_products',
                'description' => 'Consultar productos',
            ],

            // User management permissions
            [
                'name' => 'manage_users',
                'description' => 'Gestionar usuarios del sistema',
            ],
            [
                'name' => 'manage_roles',
                'description' => 'Gestionar roles y permisos',
            ],

            // Reports permissions
            [
                'name' => 'view_reports',
                'description' => 'Ver reportes del sistema',
            ],
            [
                'name' => 'export_data',
                'description' => 'Exportar datos y reportes',
            ],
        ];

        foreach ($permissions as $permissionData) {
            Permission::firstOrCreate(
                ['name' => $permissionData['name']],
                ['description' => $permissionData['description']]
            );
        }

        // Create Roles
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Administrador del sistema con acceso total']
        );

        $managerRole = Role::firstOrCreate(
            ['name' => 'manager'],
            ['description' => 'Gerente con permisos de supervisión']
        );

        $cashierRole = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['description' => 'Cajero que realiza ventas']
        );

        $warehouseRole = Role::firstOrCreate(
            ['name' => 'warehouse'],
            ['description' => 'Encargado de bodega y stock']
        );

        // Assign permissions to Admin (all permissions)
        $allPermissions = Permission::all();
        $adminRole->permissions()->sync($allPermissions->pluck('id'));

        // Assign permissions to Manager
        $managerPermissions = Permission::whereIn('name', [
            'view_stock',
            'adjust_stock',
            'sell',
            'view_sales',
            'cancel_sale',
            'annul_sale',
            'approve_annulment',
            'generate_invoice',
            'view_fiscal_documents',
            'open_cash_box',
            'close_cash_box',
            'register_expense',
            'manage_cash_movements',
            'view_cash_box',
            'view_products',
            'view_reports',
            'export_data',
        ])->get();
        $managerRole->permissions()->sync($managerPermissions->pluck('id'));

        // Assign permissions to Cashier
        $cashierPermissions = Permission::whereIn('name', [
            'view_stock',
            'sell',
            'view_sales',
            'generate_invoice',
            'view_products',
            'view_cash_box',
            'open_cash_box',
            'close_cash_box',
        ])->get();
        $cashierRole->permissions()->sync($cashierPermissions->pluck('id'));

        // Assign permissions to Warehouse
        $warehousePermissions = Permission::whereIn('name', [
            'manage_stock',
            'adjust_stock',
            'view_stock',
            'manage_products',
            'view_products',
        ])->get();
        $warehouseRole->permissions()->sync($warehousePermissions->pluck('id'));

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('Roles created: Admin, Manager, Cashier, Warehouse');
        $this->command->info('Total permissions: ' . $allPermissions->count());
    }
}
