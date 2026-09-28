<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->default('Asia/Kolkata')->after('bio');
            $table->time('work_start')->nullable()->after('timezone');
            $table->time('work_end')->nullable()->after('work_start');
            $table->string('status_emoji', 16)->nullable()->after('work_end');
            $table->string('status_text', 100)->nullable()->after('status_emoji');
            $table->timestamp('status_expires_at')->nullable()->after('status_text');
            $table->boolean('email_notifications')->default(true)->after('status_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'work_start', 'work_end', 'status_emoji', 'status_text', 'status_expires_at', 'email_notifications']);
        });
    }
};
