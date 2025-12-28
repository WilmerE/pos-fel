<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@pos.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'active' => true,
            ]
        );

        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        // Create Manager User
        $manager = User::firstOrCreate(
            ['email' => 'manager@pos.com'],
            [
                'name' => 'Gerente',
                'password' => Hash::make('password'),
                'active' => true,
            ]
        );

        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole) {
            $manager->roles()->syncWithoutDetaching([$managerRole->id]);
        }

        // Create Cashier User
        $cashier = User::firstOrCreate(
            ['email' => 'cashier@pos.com'],
            [
                'name' => 'Cajero',
                'password' => Hash::make('password'),
                'active' => true,
            ]
        );

        $cashierRole = Role::where('name', 'cashier')->first();
        if ($cashierRole) {
            $cashier->roles()->syncWithoutDetaching([$cashierRole->id]);
        }

        // Create Warehouse User
        $warehouse = User::firstOrCreate(
            ['email' => 'warehouse@pos.com'],
            [
                'name' => 'Bodeguero',
                'password' => Hash::make('password'),
                'active' => true,
            ]
        );

        $warehouseRole = Role::where('name', 'warehouse')->first();
        if ($warehouseRole) {
            $warehouse->roles()->syncWithoutDetaching([$warehouseRole->id]);
        }

        $this->command->info('Users seeded successfully!');
        $this->command->info('Admin: admin@pos.com / password');
        $this->command->info('Manager: manager@pos.com / password');
        $this->command->info('Cashier: cashier@pos.com / password');
        $this->command->info('Warehouse: warehouse@pos.com / password');
    }
}
