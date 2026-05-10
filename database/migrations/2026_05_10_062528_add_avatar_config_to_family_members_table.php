<?php

use App\Support\Avatar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->json('avatar_config')->nullable()->after('color');
        });

        DB::table('family_members')
            ->where('is_guest', true)
            ->whereNull('avatar_config')
            ->orderBy('id')
            ->each(function ($member) {
                DB::table('family_members')
                    ->where('id', $member->id)
                    ->update(['avatar_config' => json_encode(Avatar::randomConfig())]);
            });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->dropColumn('avatar_config');
        });
    }
};
