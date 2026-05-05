<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->decimal('target_calories', 8, 2)->nullable()->after('notes');
            $table->decimal('target_protein_g', 8, 2)->nullable()->after('target_calories');
            $table->decimal('target_carbs_g', 8, 2)->nullable()->after('target_protein_g');
            $table->decimal('target_fat_g', 8, 2)->nullable()->after('target_carbs_g');
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->dropColumn(['target_calories', 'target_protein_g', 'target_carbs_g', 'target_fat_g']);
        });
    }
};
