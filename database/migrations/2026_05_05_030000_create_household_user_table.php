<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->timestamps();
            $table->unique(['household_id', 'user_id']);
        });

        $rows = DB::table('users')
            ->whereNotNull('household_id')
            ->get(['id', 'household_id']);

        $now = now();
        foreach ($rows as $row) {
            DB::table('household_user')->insertOrIgnore([
                'household_id' => $row->household_id,
                'user_id' => $row->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('household_user');
    }
};
