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
        Schema::create('booking_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->boolean('is_enabled')->default(false)->index();
            $table->string('title')->default('Meet with me');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedSmallInteger('minimum_notice_hours')->default(2);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->string('timezone')->default('UTC');
            $table->time('availability_starts_at')->default('09:00');
            $table->time('availability_ends_at')->default('17:00');
            $table->json('available_days');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_pages');
    }
};
