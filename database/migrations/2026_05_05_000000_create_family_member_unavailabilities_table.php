<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_member_unavailabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_member_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('slot', ['breakfast', 'lunch', 'dinner', 'snack']);
            $table->timestamps();
            $table->unique(['family_member_id', 'date', 'slot']);
            $table->index(['date', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_member_unavailabilities');
    }
};
