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
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('google_calendar_connection_id')
                ->nullable()
                ->after('booking_page_id')
                ->constrained()
                ->nullOnDelete();
            $table->text('google_calendar_id')->nullable()->after('google_event_id');
            $table->timestamp('cancelled_at')->nullable()->after('google_event_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('google_calendar_connection_id');
            $table->dropColumn(['google_calendar_id', 'cancelled_at']);
        });
    }
};
