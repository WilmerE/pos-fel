<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Bebidas',
                'description' => 'Bebidas frías, refrescos, jugos y agua',
                'active' => true,
            ],
            [
                'name' => 'Snacks',
                'description' => 'Papas fritas, nachos y snacks salados',
                'active' => true,
            ],
            [
                'name' => 'Galletas',
                'description' => 'Galletas dulces y saladas',
                'active' => true,
            ],
            [
                'name' => 'Lácteos',
                'description' => 'Leche, yogurt y productos lácteos',
                'active' => true,
            ],
            [
                'name' => 'Panadería',
                'description' => 'Pan, pasteles y productos de panadería',
                'active' => true,
            ],
            [
                'name' => 'Higiene',
                'description' => 'Productos de higiene y cuidado personal',
                'active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }

        $this->command->info('✓ 6 categorías creadas');
    }
}
