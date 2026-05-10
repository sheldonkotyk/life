<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_member_id')->constrained('family_members')->cascadeOnDelete();
            $table->foreignId('to_member_id')->constrained('family_members')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['from_member_id', 'to_member_id', 'type']);
            $table->index('to_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_connections');
    }
};
