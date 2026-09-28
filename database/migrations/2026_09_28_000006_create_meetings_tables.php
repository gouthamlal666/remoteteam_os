<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('agenda')->nullable();
            $table->dateTime('starts_at')->index();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('meeting_link')->nullable();
            // scheduled | completed | cancelled
            $table->string('status', 20)->default('scheduled')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // pending | accepted | declined
            $table->string('response', 20)->default('pending');
            // null until the organizer takes attendance
            $table->boolean('attended')->nullable();
            $table->timestamps();
            $table->unique(['meeting_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
    }
};
