<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->decimal('calories', 8, 2)->nullable()->after('category');
            $table->decimal('protein_g', 8, 2)->nullable()->after('calories');
            $table->decimal('carbs_g', 8, 2)->nullable()->after('protein_g');
            $table->decimal('fat_g', 8, 2)->nullable()->after('carbs_g');
        });
    }

    public function down(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->dropColumn(['calories', 'protein_g', 'carbs_g', 'fat_g']);
        });
    }
};
