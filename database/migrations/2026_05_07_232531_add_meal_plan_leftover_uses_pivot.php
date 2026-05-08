<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_plan_leftover_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_meal_plan_id')->constrained('meal_plans')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['meal_plan_id', 'source_meal_plan_id'], 'meal_plan_leftover_uses_unique');
        });

        foreach (DB::table('meal_plans')->whereNotNull('leftover_of_id')->orderBy('id')->get() as $row) {
            DB::table('meal_plan_leftover_uses')->insert([
                'meal_plan_id' => $row->id,
                'source_meal_plan_id' => $row->leftover_of_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('meal_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leftover_of_id');
        });
    }

    public function down(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->foreignId('leftover_of_id')->nullable()->after('recipe_id')->constrained('meal_plans')->nullOnDelete();
        });

        foreach (DB::table('meal_plan_leftover_uses')->orderBy('id')->get() as $row) {
            DB::table('meal_plans')->where('id', $row->meal_plan_id)
                ->whereNull('leftover_of_id')
                ->update(['leftover_of_id' => $row->source_meal_plan_id]);
        }

        Schema::dropIfExists('meal_plan_leftover_uses');
    }
};
