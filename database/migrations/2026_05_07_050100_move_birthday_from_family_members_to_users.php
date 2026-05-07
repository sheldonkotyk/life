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
            $table->date('birthday')->nullable()->after('email');
        });

        DB::table('family_members')
            ->whereNotNull('user_id')
            ->whereNotNull('birthday')
            ->orderBy('id')
            ->each(function ($m) {
                DB::table('users')->where('id', $m->user_id)->update(['birthday' => $m->birthday]);
            });

        Schema::table('family_members', function (Blueprint $table) {
            $table->dropColumn('birthday');
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->date('birthday')->nullable();
        });

        DB::table('users')
            ->whereNotNull('birthday')
            ->orderBy('id')
            ->each(function ($u) {
                DB::table('family_members')->where('user_id', $u->id)->update(['birthday' => $u->birthday]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('birthday');
        });
    }
};
