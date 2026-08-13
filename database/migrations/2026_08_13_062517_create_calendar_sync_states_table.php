<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where each destination calendar's incremental sync position is kept, so a
     * poll asks Google only for what changed since the last one.
     */
    public function up(): void
    {
        Schema::create('calendar_sync_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('google_calendar_connection_id')->constrained()->cascadeOnDelete();
            $table->text('google_calendar_id');
            $table->text('sync_token')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['google_calendar_connection_id', 'google_calendar_id'], 'calendar_sync_states_calendar_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_sync_states');
    }
};
