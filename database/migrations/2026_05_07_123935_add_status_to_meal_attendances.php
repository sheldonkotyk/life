<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_attendances', function (Blueprint $table) {
            $table->string('status', 20)->default('eating')->after('family_member_id');
        });
    }

    public function down(): void
    {
        Schema::table('meal_attendances', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
