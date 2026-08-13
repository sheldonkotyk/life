<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A page can ask for the owner's approval before a request becomes a
     * meeting; until they answer, the booking holds the slot without an event.
     */
    public function up(): void
    {
        Schema::table('booking_pages', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('is_enabled');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('responded_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('booking_pages', function (Blueprint $table) {
            $table->dropColumn('requires_approval');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('responded_at');
        });
    }
};
