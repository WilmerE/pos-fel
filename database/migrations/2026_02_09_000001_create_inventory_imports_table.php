<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_name'); // "Farmacia Gualanteca - Feb 2026"
            $table->enum('source_type', ['excel', 'json', 'manual'])->default('json');
            $table->integer('total_items')->default(0); // Total de items en el archivo
            $table->integer('total_products_created')->default(0);
            $table->integer('total_products_updated')->default(0);
            $table->integer('total_categories_created')->default(0);
            $table->integer('total_suppliers_created')->default(0);
            $table->integer('total_presentations_created')->default(0);
            $table->integer('total_stock_batches_created')->default(0);
            $table->foreignId('imported_by')->constrained('users');
            $table->timestamp('imported_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_imports');
    }
};
