<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Organisation-wide settings, edited by super admins on the System settings page.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('whatsapp_notifications')->default(false)->after('email_notifications');
        });

        // Delivery log so super admins can see what was sent and why something failed.
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to', 30);
            $table->string('type', 50);
            $table->text('body');
            $table->string('driver', 20);
            // logged | sent | failed
            $table->string('status', 20);
            $table->string('provider_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('whatsapp_notifications'));
        Schema::dropIfExists('settings');
    }
};
