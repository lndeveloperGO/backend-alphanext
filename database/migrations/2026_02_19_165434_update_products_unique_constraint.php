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
            // Drop foreign key first because it depends on the index we want to drop
            $table->dropForeign(['package_id']);
            
            // Drop old unique index
            $table->dropUnique('products_package_id_unique');
            
            // Add new composite unique index
            $table->unique(['package_id', 'grants_answer_key']);
            
            // Re-add foreign key constraint
            $table->foreign('package_id')->references('id')->on('packages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropUnique(['package_id', 'grants_answer_key']);
            
            $table->unique('package_id');
            $table->foreign('package_id')->references('id')->on('packages')->cascadeOnDelete();
        });
    }
};
