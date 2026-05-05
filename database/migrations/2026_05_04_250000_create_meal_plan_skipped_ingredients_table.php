<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_plan_skipped_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_ingredient_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['meal_plan_id', 'recipe_ingredient_id'], 'mp_skip_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plan_skipped_ingredients');
    }
};
