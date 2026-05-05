<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_recipes', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('themealdb');
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('area')->nullable();
            $table->text('instructions')->nullable();
            $table->string('image_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('source_url')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index('name');
            $table->index('category');
            $table->index('area');
        });

        Schema::create('global_recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_recipe_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('measure')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_recipe_ingredients');
        Schema::dropIfExists('global_recipes');
    }
};
