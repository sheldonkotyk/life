<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 32)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('todo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_list_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_family_member_id')->nullable()->constrained('family_members')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('recurrence_frequency', 16)->nullable();
            $table->unsignedSmallInteger('recurrence_interval')->nullable();
            $table->date('recurrence_until')->nullable();
            $table->timestamps();

            $table->index(['todo_list_id', 'completed_at']);
        });

        Schema::create('todo_item_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_member_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['todo_item_id', 'family_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_item_assignments');
        Schema::dropIfExists('todo_items');
        Schema::dropIfExists('todo_lists');
    }
};
