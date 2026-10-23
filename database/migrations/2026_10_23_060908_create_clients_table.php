<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('legal_name')->nullable();
            // active | prospect | paused | former
            $table->string('status', 20)->default('active')->index();
            $table->string('industry', 100)->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('address_line')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->date('client_since')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('client_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('website');
            $table->string('label');
            $table->string('url', 2048);
            $table->timestamps();
        });

        // Password vault: secrets are encrypted at rest with the app key.
        Schema::create('client_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('url', 2048)->nullable();
            $table->string('username')->nullable();
            $table->text('password');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('client_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30)->default('other');
            $table->string('title');
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('name')->constrained()->nullOnDelete();
        });

        // Turn the old free-text client names into client records ("Internal" means no client).
        $names = DB::table('projects')->whereNotNull('client')->where('client', '!=', '')->where('client', '!=', 'Internal')
            ->distinct()->pluck('client');

        foreach ($names as $name) {
            $id = DB::table('clients')->insertGetId(['name' => trim($name), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('projects')->where('client', $name)->update(['client_id' => $id]);
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('client');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('client')->nullable()->after('name');
        });

        foreach (DB::table('clients')->get(['id', 'name']) as $client) {
            DB::table('projects')->where('client_id', $client->id)->update(['client' => $client->name]);
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });

        Schema::dropIfExists('client_files');
        Schema::dropIfExists('client_credentials');
        Schema::dropIfExists('client_links');
        Schema::dropIfExists('client_contacts');
        Schema::dropIfExists('clients');
    }
};
