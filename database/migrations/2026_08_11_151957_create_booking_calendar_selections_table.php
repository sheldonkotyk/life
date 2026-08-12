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
        Schema::create('booking_calendar_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('google_calendar_connection_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->text('google_calendar_id');
            $table->string('google_calendar_name');
            $table->boolean('checks_conflicts')->default(true);
            $table->boolean('receives_bookings')->default(false);
            $table->timestamps();

            $table->unique(
                ['booking_page_id', 'google_calendar_connection_id', 'google_calendar_id'],
                'booking_calendar_selections_unique',
            );
            $table->index(['booking_page_id', 'checks_conflicts']);
            $table->index(['booking_page_id', 'receives_bookings']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_calendar_selections');
    }
};
