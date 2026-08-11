<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opting out used to be stored as a null `daily_today_email_at`, which is
     * indistinguishable from never having configured a time. A backfill of that
     * column re-subscribed everyone who had turned the digest off, so the opt-in
     * now lives in its own column that a time backfill cannot flip.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('daily_today_email_enabled')->default(false)->after('daily_today_email_at');
        });

        DB::table('users')
            ->whereNotNull('daily_today_email_at')
            ->update(['daily_today_email_enabled' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->time('daily_today_email_at')->default(null)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('daily_today_email_enabled');
        });
    }
};
