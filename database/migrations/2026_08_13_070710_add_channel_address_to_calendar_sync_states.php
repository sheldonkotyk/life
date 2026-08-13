<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which URL a channel points at. A database copied from production carries
     * production's channels, and this is what stops a local run from closing
     * them.
     */
    public function up(): void
    {
        Schema::table('calendar_sync_states', function (Blueprint $table) {
            $table->text('channel_address')->nullable()->after('channel_token');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_sync_states', function (Blueprint $table) {
            $table->dropColumn('channel_address');
        });
    }
};
