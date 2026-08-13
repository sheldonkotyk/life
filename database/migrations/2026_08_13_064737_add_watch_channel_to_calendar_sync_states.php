<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Google's push channels live beside the sync position they trigger: one
     * channel per calendar we reconcile, renewed before it expires.
     */
    public function up(): void
    {
        Schema::table('calendar_sync_states', function (Blueprint $table) {
            $table->uuid('channel_id')->nullable()->unique()->after('sync_token');
            $table->string('channel_resource_id')->nullable()->after('channel_id');
            $table->string('channel_token')->nullable()->after('channel_resource_id');
            $table->timestamp('channel_expires_at')->nullable()->after('channel_token');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_sync_states', function (Blueprint $table) {
            $table->dropColumn(['channel_id', 'channel_resource_id', 'channel_token', 'channel_expires_at']);
        });
    }
};
