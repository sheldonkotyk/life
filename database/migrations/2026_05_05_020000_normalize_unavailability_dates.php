<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('family_member_unavailabilities')
            ->where('date', 'like', '% %')
            ->update(['date' => DB::raw("substr(date, 1, 10)")]);
    }

    public function down(): void
    {
    }
};
