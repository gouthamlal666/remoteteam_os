<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('name', 150);
            // national | kerala | festival | company
            $table->string('type', 20)->default('kerala');
            // Optional (restricted) holidays are not days off unless leave is taken.
            $table->boolean('is_optional')->default(false);
            // Lunar-calendar festivals need their date confirmed against the official list each year.
            $table->boolean('needs_confirmation')->default(false);
            $table->timestamps();
            $table->unique(['date', 'name']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('code', 10)->unique();
            // null = no limit (e.g. Loss of Pay)
            $table->decimal('days_per_year', 5, 1)->nullable();
            $table->string('color', 20)->default('sky');
            $table->boolean('allow_half_day')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            // first_half | second_half (single-day requests only)
            $table->string('half_day', 12)->nullable();
            $table->decimal('days', 5, 1);
            $table->text('reason');
            // pending | approved | rejected | cancelled
            $table->string('status', 12)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'start_date']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The person's local date (their timezone).
            $table->date('date');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            // present | half_day | absent
            $table->string('status', 12)->default('present');
            $table->boolean('is_late')->default(false);
            $table->unsignedSmallInteger('minutes_late')->default(0);
            // auto (from signing in) | manual (corrected by an admin)
            $table->string('source', 10)->default('auto');
            $table->string('note')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
            $table->index('date');
        });

        $now = now();

        DB::table('leave_types')->insert([
            ['name' => 'Casual Leave', 'code' => 'CL', 'days_per_year' => 12, 'color' => 'sky', 'allow_half_day' => true, 'position' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sick Leave', 'code' => 'SL', 'days_per_year' => 12, 'color' => 'rose', 'allow_half_day' => true, 'position' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Earned Leave', 'code' => 'EL', 'days_per_year' => 12, 'color' => 'emerald', 'allow_half_day' => false, 'position' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Loss of Pay', 'code' => 'LOP', 'days_per_year' => null, 'color' => 'amber', 'allow_half_day' => true, 'position' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 2026 holidays. Fixed-date and Easter-based dates are certain; lunar-calendar festivals
        // are best estimates flagged "needs confirmation" until checked against the Kerala Government list.
        $holidays = [
            ['2026-01-02', 'Mannam Jayanthi', 'kerala', false],
            ['2026-01-26', 'Republic Day', 'national', false],
            ['2026-02-15', 'Maha Shivaratri', 'festival', true],
            ['2026-03-20', 'Eid ul-Fitr (Ramzan)', 'festival', true],
            ['2026-04-02', 'Maundy Thursday', 'kerala', false],
            ['2026-04-03', 'Good Friday', 'national', false],
            ['2026-04-14', 'Dr. B.R. Ambedkar Jayanti', 'national', false],
            ['2026-04-15', 'Vishu', 'kerala', true],
            ['2026-05-01', 'May Day', 'kerala', false],
            ['2026-05-27', 'Bakrid (Eid al-Adha)', 'festival', true],
            ['2026-06-26', 'Muharram', 'festival', true],
            ['2026-08-12', 'Karkidaka Vavu', 'kerala', true],
            ['2026-08-15', 'Independence Day', 'national', false],
            ['2026-08-25', 'First Onam (Uthradom)', 'kerala', true],
            ['2026-08-26', 'Thiruvonam', 'kerala', true],
            ['2026-08-27', 'Third Onam', 'kerala', true],
            ['2026-08-28', 'Ayyankali Jayanthi', 'kerala', false],
            ['2026-08-29', 'Sree Narayana Guru Jayanthi', 'kerala', true],
            ['2026-08-26', 'Milad-i-Sherif', 'festival', true],
            ['2026-09-04', 'Sree Krishna Jayanthi', 'festival', true],
            ['2026-09-21', 'Sree Narayana Guru Samadhi', 'kerala', true],
            ['2026-10-02', 'Gandhi Jayanti', 'national', false],
            ['2026-10-19', 'Mahanavami', 'festival', true],
            ['2026-10-20', 'Vijayadashami', 'festival', true],
            ['2026-11-08', 'Deepavali', 'festival', true],
            ['2026-12-25', 'Christmas', 'national', false],
        ];

        DB::table('holidays')->insert(array_map(fn ($h) => [
            'date' => $h[0], 'name' => $h[1], 'type' => $h[2], 'is_optional' => false,
            'needs_confirmation' => $h[3], 'created_at' => $now, 'updated_at' => $now,
        ], $holidays));
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('holidays');
    }
};
