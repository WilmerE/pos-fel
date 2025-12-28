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
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('batch_number');
            $table->date('expiration_date')->nullable();
            $table->integer('quantity_initial');
            $table->integer('quantity_available');
            $table->string('location')->nullable(); // ubicación física
            $table->timestamps();
            
            $table->index(['product_id', 'expiration_date']);
            $table->index('quantity_available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
