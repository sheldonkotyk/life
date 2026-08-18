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
        Schema::table('google_calendar_connections', function (Blueprint $table) {
            $table->string('google_name')->nullable()->after('google_email');
            $table->string('google_avatar_url')->nullable()->after('google_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_calendar_connections', function (Blueprint $table) {
            $table->dropColumn(['google_name', 'google_avatar_url']);
        });
    }
};
