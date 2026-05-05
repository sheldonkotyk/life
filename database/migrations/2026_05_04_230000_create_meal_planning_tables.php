<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#6366f1');
            $table->boolean('is_child')->default(false);
            $table->date('birthday')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('food_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_member_id')->constrained()->cascadeOnDelete();
            $table->string('food');
            $table->enum('type', ['like', 'dislike', 'allergy']);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['family_member_id', 'type']);
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('servings')->default(5);
            $table->integer('prep_minutes')->nullable();
            $table->string('source_url')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('makes_leftovers')->default(false);
            $table->integer('default_leftover_servings')->default(0);
            $table->json('tags')->nullable();
            $table->timestamps();
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('quantity')->nullable();
            $table->string('unit')->nullable();
            $table->string('category')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('recipe_member_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_member_id')->constrained()->cascadeOnDelete();
            $table->enum('rating', ['love', 'ok', 'dislike'])->default('ok');
            $table->timestamps();
            $table->unique(['recipe_id', 'family_member_id']);
        });

        Schema::create('meal_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('slot', ['breakfast', 'lunch', 'dinner', 'snack']);
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leftover_of_id')->nullable()->constrained('meal_plans')->nullOnDelete();
            $table->string('custom_name')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('save_leftovers')->default(false);
            $table->integer('leftover_servings')->nullable();
            $table->timestamps();
            $table->index(['household_id', 'date']);
        });

        Schema::create('meal_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_member_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['meal_plan_id', 'family_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_attendances');
        Schema::dropIfExists('meal_plans');
        Schema::dropIfExists('recipe_member_ratings');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('food_preferences');
        Schema::dropIfExists('family_members');
    }
};
