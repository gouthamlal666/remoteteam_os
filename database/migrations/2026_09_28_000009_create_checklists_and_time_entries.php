<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            // null while the timer is running
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('minutes')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'ended_at']);
            $table->index(['task_id']);
        });

        // Reminder bookkeeping so scheduled reminders are only sent once.
        Schema::table('meetings', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('notes');
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->date('due_reminded_on')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('due_reminded_on'));
        Schema::table('meetings', fn (Blueprint $table) => $table->dropColumn('reminded_at'));
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('task_checklist_items');
    }
};
