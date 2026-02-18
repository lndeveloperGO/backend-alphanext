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
        Schema::table('questions', function (Blueprint $table) {
            $table->string('image')->nullable()->after('question');
            $table->string('question_type')->default('text')->after('image');
        });

        Schema::table('question_options', function (Blueprint $table) {
            $table->string('image')->nullable()->after('text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['image', 'question_type']);
        });

        Schema::table('question_options', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
