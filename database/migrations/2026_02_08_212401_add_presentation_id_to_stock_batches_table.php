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
        Schema::table('stock_batches', function (Blueprint $table) {
            $table->foreignId('presentation_id')->nullable()->after('product_id')->constrained('product_presentations')->onDelete('cascade');
            
            $table->index('presentation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_batches', function (Blueprint $table) {
            $table->dropForeign(['presentation_id']);
            $table->dropIndex(['presentation_id']);
            $table->dropColumn('presentation_id');
        });
    }
};
