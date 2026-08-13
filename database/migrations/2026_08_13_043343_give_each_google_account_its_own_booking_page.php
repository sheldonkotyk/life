<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A booking page now belongs to the Google account whose calendars it reads
     * and writes, so a user with several accounts gets a page for each.
     */
    public function up(): void
    {
        Schema::table('booking_pages', function (Blueprint $table) {
            $table->foreignId('google_calendar_connection_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        // Adopt each existing page into the account that was receiving its
        // bookings, falling back to the owner's first connected account.
        DB::table('booking_pages')->orderBy('id')->each(function (object $page): void {
            $connectionId = DB::table('booking_calendar_selections')
                ->where('booking_page_id', $page->id)
                ->where('receives_bookings', true)
                ->value('google_calendar_connection_id')
                ?? DB::table('google_calendar_connections')
                    ->where('user_id', $page->user_id)
                    ->orderBy('id')
                    ->value('id');

            DB::table('booking_pages')
                ->where('id', $page->id)
                ->update(['google_calendar_connection_id' => $connectionId]);
        });

        Schema::table('booking_pages', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->unique('google_calendar_connection_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_pages', function (Blueprint $table) {
            $table->dropUnique(['google_calendar_connection_id']);
            $table->dropConstrainedForeignId('google_calendar_connection_id');
            $table->unique('user_id');
        });
    }
};
