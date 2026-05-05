<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $householdsWithoutAdmin = DB::table('household_user')
            ->select('household_id')
            ->groupBy('household_id')
            ->havingRaw("SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) = 0")
            ->pluck('household_id');

        foreach ($householdsWithoutAdmin as $householdId) {
            $earliest = DB::table('household_user')
                ->where('household_id', $householdId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->first();

            if ($earliest) {
                DB::table('household_user')
                    ->where('id', $earliest->id)
                    ->update(['role' => 'admin', 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Not reversible — leave roles in place.
    }
};
