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
        Schema::table('product_presentations', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->default(0)->after('factor');
            // Rename 'price' to 'sale_price' for clarity
            $table->renameColumn('price', 'sale_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_presentations', function (Blueprint $table) {
            $table->renameColumn('sale_price', 'price');
            $table->dropColumn('purchase_price');
        });
    }
};
