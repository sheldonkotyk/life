<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->time('breakfast_start_time')->default('07:00');
            $table->time('breakfast_end_time')->default('09:00');
            $table->time('lunch_start_time')->default('11:30');
            $table->time('lunch_end_time')->default('13:30');
            $table->time('dinner_start_time')->default('17:30');
            $table->time('dinner_end_time')->default('19:30');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn([
                'breakfast_start_time',
                'breakfast_end_time',
                'lunch_start_time',
                'lunch_end_time',
                'dinner_start_time',
                'dinner_end_time',
            ]);
        });
    }
};
