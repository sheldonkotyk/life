<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('daily_today_email_at')->default('00:00:00')->nullable()->change();
        });

        DB::table('users')
            ->where('daily_today_email_at', '04:00:00')
            ->update(['daily_today_email_at' => '00:00:00']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('daily_today_email_at')->default('04:00:00')->nullable()->change();
        });
    }
};
