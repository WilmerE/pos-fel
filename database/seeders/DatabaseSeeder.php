<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed in correct order to respect foreign key constraints
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,     // Categories must be created before products
            SupplierSeeder::class,     // Suppliers must be created before products
            ProductSeeder::class,
            StockSeeder::class,
        ]);
    }
}
