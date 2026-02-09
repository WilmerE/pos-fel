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
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('name');
            $table->string('location')->nullable()->after('description');
            $table->foreignId('supplier_id')->nullable()->after('category_id')->constrained('suppliers')->onDelete('set null');
            
            $table->index('brand');
            $table->index('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropIndex(['brand']);
            $table->dropIndex(['location']);
            $table->dropColumn(['brand', 'location', 'supplier_id']);
        });
    }
};
