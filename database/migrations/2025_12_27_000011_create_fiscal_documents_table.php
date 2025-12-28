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
        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            $table->string('uuid')->unique()->nullable(); // UUID de la SAT
            $table->string('serie')->nullable();
            $table->string('number')->nullable();
            $table->enum('status', ['pending', 'authorized', 'annulled', 'rejected'])->default('pending');
            $table->longText('xml')->nullable(); // XML del documento FEL
            $table->string('pdf_path')->nullable(); // ruta del PDF generado
            $table->timestamps();
            
            $table->index('uuid');
            $table->index(['status', 'created_at']);
            $table->index('sale_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_documents');
    }
};
