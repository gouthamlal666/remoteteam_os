<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drive folders, nestable (parent_id) like Google Drive.
        Schema::create('client_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('client_folders')->cascadeOnDelete();
            $table->string('name', 100);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Drive items are either uploaded files or saved links (Google Drive, YouTube, Vimeo, Loom…).
        Schema::table('client_files', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('client_id')->constrained('client_folders')->nullOnDelete();
            $table->string('kind', 10)->default('file')->after('folder_id');
            $table->string('url', 2048)->nullable()->after('path');
            $table->string('path')->nullable()->change();
            $table->string('original_name')->nullable()->change();
        });

        // Free-form "extra details", e.g. Hosting: Hostinger, Domain expires: 12 Mar 2027.
        Schema::create('client_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->text('value');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('client_credentials', function (Blueprint $table) {
            $table->string('category', 20)->default('other')->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('client_credentials', fn (Blueprint $table) => $table->dropColumn('category'));
        Schema::dropIfExists('client_details');
        Schema::table('client_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
            $table->dropColumn(['kind', 'url']);
        });
        Schema::dropIfExists('client_folders');
    }
};
