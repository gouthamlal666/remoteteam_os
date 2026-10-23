<?php

namespace Tests\Feature;

use App\Livewire\Attendance\Index as AttendancePage;
use App\Livewire\Leave\Index as LeavePage;
use App\Models\AttendanceRecord;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\LeaveService;
use App\Services\WorkCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * October 2026: Thu 1st, Gandhi Jayanti Fri 2nd, 2nd Saturday the 10th,
 * Mahanavami / Vijayadashami on the 19th–20th (seeded by the migration).
 */
class AttendanceLeaveTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private User $member;

    private LeaveType $casual;

    protected function setUp(): void
    {
        parent::setUp();
        // Monday 5 Oct 2026, 09:05 in Kerala (work starts 09:00, 15 min grace).
        $this->travelTo(Carbon::parse('2026-10-05 03:35:00', 'UTC'));

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->admin = User::factory()->admin()->create();
        $this->member = User::factory()->create();
        $this->casual = LeaveType::where('code', 'CL')->firstOrFail();
    }

    private function leave(): LeaveService
    {
        return app(LeaveService::class);
    }

    private function applyFor(User $user, string $start, ?string $end = null, array $extra = []): LeaveRequest
    {
        return $this->leave()->apply($user, $extra + [
            'leave_type_id' => $this->casual->id, 'start_date' => $start, 'end_date' => $end ?? $start, 'half_day' => null, 'reason' => 'Family function',
        ]);
    }

    private function assertLeaveError(string $field, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a validation error on '.$field);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    // ── Calendar ───────────────────────────────────────────────

    public function test_sundays_second_saturdays_and_holidays_are_off(): void
    {
        $calendar = app(WorkCalendar::class);

        $this->assertFalse($calendar->isWorkingDay(Carbon::parse('2026-10-04')), 'Sunday');
        $this->assertFalse($calendar->isWorkingDay(Carbon::parse('2026-10-10')), '2nd Saturday');
        $this->assertTrue($calendar->isWorkingDay(Carbon::parse('2026-10-03')), '1st Saturday');
        $this->assertTrue($calendar->isWorkingDay(Carbon::parse('2026-10-17')), '3rd Saturday');
        $this->assertFalse($calendar->isWorkingDay(Carbon::parse('2026-10-02')), 'Gandhi Jayanti');
        $this->assertSame('2nd Saturday off', $calendar->describe(Carbon::parse('2026-10-10'))['label']);
    }

    public function test_optional_holidays_are_not_days_off(): void
    {
        Holiday::create(['date' => '2026-10-07', 'name' => 'Restricted day', 'type' => 'festival', 'is_optional' => true]);

        $this->assertTrue(app(WorkCalendar::class)->isWorkingDay(Carbon::parse('2026-10-07')));
    }

    // ── Applying ───────────────────────────────────────────────

    public function test_leave_only_counts_working_days(): void
    {
        // Fri 9 → Mon 12: Sat 10 is the 2nd Saturday and Sun 11 is off.
        $this->assertSame(2.0, $this->leave()->countDays(Carbon::parse('2026-10-09'), Carbon::parse('2026-10-12'), null));
        // Mon 19 → Wed 21: the 19th and 20th are holidays.
        $this->assertSame(1.0, $this->leave()->countDays(Carbon::parse('2026-10-19'), Carbon::parse('2026-10-21'), null));
        $this->assertSame(0.5, $this->leave()->countDays(Carbon::parse('2026-10-07'), Carbon::parse('2026-10-07'), 'first_half'));
    }

    public function test_applying_notifies_approvers_and_holds_balance(): void
    {
        $request = $this->applyFor($this->member, '2026-10-09', '2026-10-12');

        $this->assertSame('pending', $request->status);
        $this->assertEquals(2, $request->days);
        $this->assertSame(1, $this->admin->notifications()->count());
        $this->assertSame(1, $this->superAdmin->notifications()->count());
        $this->assertSame(0, $this->member->notifications()->count());

        $balance = $this->leave()->balance($this->member, $this->casual, 2026);
        $this->assertEquals(2, $balance['pending']);
        $this->assertEquals(10, $balance['remaining']);
    }

    public function test_admin_leave_goes_only_to_super_admins(): void
    {
        $this->applyFor($this->admin, '2026-10-07');

        $this->assertSame(1, $this->superAdmin->notifications()->count());
        $this->assertSame(0, $this->admin->notifications()->count());
    }

    public function test_apply_validations(): void
    {
        $this->assertLeaveError('start_date', fn () => $this->applyFor($this->member, '2026-10-10', '2026-10-11'));
        $this->assertLeaveError('end_date', fn () => $this->applyFor($this->member, '2026-10-08', '2026-10-07'));
        $this->assertLeaveError('half_day', fn () => $this->applyFor($this->member, '2026-10-07', '2026-10-08', ['half_day' => 'first_half']));
        $this->assertLeaveError('start_date', fn () => $this->applyFor($this->member, '2026-08-01'));

        $this->applyFor($this->member, '2026-10-07');
        $this->assertLeaveError('start_date', fn () => $this->applyFor($this->member, '2026-10-06', '2026-10-08'));
    }

    public function test_cannot_take_more_than_the_balance(): void
    {
        $this->casual->update(['days_per_year' => 2]);

        $this->assertLeaveError('leave_type_id', fn () => $this->applyFor($this->member, '2026-10-06', '2026-10-08'));

        // Loss of pay has no limit.
        $lop = LeaveType::where('code', 'LOP')->firstOrFail();
        $request = $this->applyFor($this->member, '2026-10-06', '2026-10-08', ['leave_type_id' => $lop->id]);
        $this->assertEquals(3, $request->days);
    }

    public function test_apply_through_the_page(): void
    {
        Livewire::actingAs($this->member)->test(LeavePage::class)
            ->call('openApply', '2026-10-07')
            ->set('reason', 'Doctor visit')
            ->set('half_day', 'second_half')
            ->call('apply')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('leave_requests', ['user_id' => $this->member->id, 'half_day' => 'second_half', 'days' => 0.5]);

        Livewire::actingAs($this->member)->test(LeavePage::class)
            ->call('openApply', '2026-10-11')->set('reason', 'Sunday')->call('apply')
            ->assertHasErrors('start_date');
    }

    // ── Reviewing ──────────────────────────────────────────────

    public function test_admin_approves_member_and_member_is_notified(): void
    {
        $request = $this->applyFor($this->member, '2026-10-07');

        Livewire::actingAs($this->admin)->test(LeavePage::class, ['tab' => 'approvals'])
            ->assertSee($this->member->name)
            ->call('openReview', $request->id, 'approve')
            ->call('saveReview')
            ->assertHasNoErrors();

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame($this->admin->id, $request->fresh()->reviewed_by);
        $this->assertSame(1, $this->member->notifications()->count());
    }

    public function test_rejecting_needs_a_reason(): void
    {
        $request = $this->applyFor($this->member, '2026-10-07');

        Livewire::actingAs($this->admin)->test(LeavePage::class)
            ->call('openReview', $request->id, 'reject')
            ->call('saveReview')->assertHasErrors('reviewNote')
            ->set('reviewNote', 'Release week')->call('saveReview')->assertHasNoErrors();

        $this->assertSame('rejected', $request->fresh()->status);
    }

    public function test_review_permissions(): void
    {
        $memberLeave = $this->applyFor($this->member, '2026-10-07');
        $adminLeave = $this->applyFor($this->admin, '2026-10-07');
        $otherAdmin = User::factory()->admin()->create();

        $this->assertFalse($this->member->can('review', $memberLeave), 'members cannot approve');
        $this->assertFalse($this->admin->can('review', $adminLeave), 'nobody approves their own leave');
        $this->assertFalse($otherAdmin->can('review', $adminLeave), 'admins cannot approve other admins');
        $this->assertTrue($this->superAdmin->can('review', $adminLeave));

        Livewire::actingAs($this->admin)->test(LeavePage::class)
            ->call('openReview', $adminLeave->id, 'approve')->assertForbidden();
        Livewire::actingAs($this->member)->test(LeavePage::class, ['tab' => 'approvals'])
            ->assertSet('tab', 'mine');
    }

    public function test_a_request_can_only_be_reviewed_once(): void
    {
        $request = $this->applyFor($this->member, '2026-10-07');
        $this->leave()->approve($this->admin, $request, null);

        $this->assertLeaveError('review', fn () => $this->leave()->reject($this->superAdmin, $request->fresh(), 'No'));
    }

    public function test_cancel_rules(): void
    {
        $pending = $this->applyFor($this->member, '2026-10-07');
        $this->assertFalse($this->leave()->canCancel($this->admin, $pending), 'only the requester');
        $this->leave()->cancel($this->member, $pending);
        $this->assertSame('cancelled', $pending->fresh()->status);

        $started = $this->applyFor($this->member, '2026-10-05');
        $this->leave()->approve($this->admin, $started, null);
        $this->assertFalse($this->leave()->canCancel($this->member, $started->fresh()), 'approved leave that has started');

        $future = $this->applyFor($this->member, '2026-10-08');
        $this->leave()->approve($this->admin, $future, null);
        $this->assertTrue($this->leave()->canCancel($this->member, $future->fresh()));
    }

    // ── Attendance ─────────────────────────────────────────────

    public function test_using_the_system_marks_you_present(): void
    {
        $this->actingAs($this->member)->get(route('dashboard'))->assertOk();
        $this->actingAs($this->member)->get(route('tasks.index'))->assertOk();

        $records = AttendanceRecord::where('user_id', $this->member->id)->get();
        $this->assertCount(1, $records);
        $this->assertSame('present', $records->first()->status);
        $this->assertFalse($records->first()->is_late);
    }

    public function test_signing_in_after_the_grace_period_is_late(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 05:00:00', 'UTC')); // 10:30 in Kerala

        $this->actingAs($this->member)->get(route('dashboard'));

        $record = AttendanceRecord::where('user_id', $this->member->id)->firstOrFail();
        $this->assertTrue($record->is_late);
        $this->assertSame(90, $record->minutes_late);
    }

    public function test_month_summary_counts_absent_leave_and_holidays(): void
    {
        $service = app(AttendanceService::class);
        $this->travelTo(Carbon::parse('2026-10-09 10:00:00', 'UTC'));

        $this->member->forceFill(['created_at' => '2026-09-01 00:00:00'])->save();

        // Thu 1 present, Fri 2 holiday, Sat 3 nothing (absent), Mon 5 leave, Tue 6 present, Wed 7 nothing, Thu 8 present.
        foreach (['2026-10-01', '2026-10-06', '2026-10-08'] as $day) {
            AttendanceRecord::factory()->for($this->member)->create(['date' => $day, 'check_in_at' => Carbon::parse($day.' 03:30', 'UTC')]);
        }
        LeaveRequest::factory()->for($this->member)->create(['leave_type_id' => $this->casual->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-05', 'status' => 'approved']);

        $row = $service->month(collect([$this->member]), Carbon::parse('2026-10-01'))->first();

        $this->assertSame('H', $row['days']['2026-10-02']['code']);
        $this->assertSame('W', $row['days']['2026-10-04']['code']);
        $this->assertSame('LV', $row['days']['2026-10-05']['code']);
        $this->assertSame('A', $row['days']['2026-10-07']['code']);
        $this->assertEquals(3, $row['summary']['present']);
        $this->assertEquals(1, $row['summary']['leave']);
        $this->assertSame(2, $row['summary']['absent']); // the 3rd and the 7th
    }

    public function test_days_before_tracking_began_or_before_joining_are_not_absent(): void
    {
        $this->travelTo(Carbon::parse('2026-10-09 10:00:00', 'UTC'));
        AttendanceRecord::factory()->for($this->admin)->create(['date' => '2026-10-06']);
        $this->member->forceFill(['created_at' => '2026-10-07 05:00:00'])->save();

        $rows = app(AttendanceService::class)->month(collect([$this->admin, $this->member]), Carbon::parse('2026-10-01'))->keyBy(fn ($r) => $r['user']->id);

        $this->assertSame('', $rows[$this->admin->id]['days']['2026-10-05']['code']);
        $this->assertSame('A', $rows[$this->admin->id]['days']['2026-10-07']['code']);
        $this->assertSame(2, $rows[$this->admin->id]['summary']['absent']); // the 7th and 8th
        $this->assertSame('', $rows[$this->member->id]['days']['2026-10-06']['code']);
        $this->assertSame('A', $rows[$this->member->id]['days']['2026-10-07']['code']);
    }

    public function test_admin_can_correct_a_member_but_not_themselves(): void
    {
        Livewire::actingAs($this->admin)->test(AttendancePage::class, ['tab' => 'month'])
            ->call('openCorrection', $this->member->id, '2026-10-01')
            ->set('corrStatus', 'present')
            ->call('saveCorrection')->assertHasErrors('corrNote')
            ->set('corrNote', 'Worked from client site')
            ->call('saveCorrection')->assertHasNoErrors();

        $this->assertDatabaseHas('attendance_records', ['user_id' => $this->member->id, 'source' => 'manual', 'marked_by' => $this->admin->id]);

        Livewire::actingAs($this->admin)->test(AttendancePage::class)
            ->call('openCorrection', $this->admin->id, '2026-10-01')->assertForbidden();
        Livewire::actingAs($this->admin)->test(AttendancePage::class)
            ->call('openCorrection', $this->superAdmin->id, '2026-10-01')->assertForbidden();
        Livewire::actingAs($this->member)->test(AttendancePage::class)
            ->call('openCorrection', $this->admin->id, '2026-10-01')->assertForbidden();
    }

    public function test_pages_render_for_everyone(): void
    {
        foreach ([$this->superAdmin, $this->member] as $user) {
            foreach (['mine', 'today', 'month', 'holidays'] as $tab) {
                $this->actingAs($user)->get(route('attendance.index', ['tab' => $tab]))->assertOk();
            }
            foreach (['mine', 'approvals', 'all', 'types'] as $tab) {
                $this->actingAs($user)->get(route('leave.index', ['tab' => $tab]))->assertOk();
            }
        }
    }

    public function test_only_managers_export_csv(): void
    {
        $this->actingAs($this->member)->get(route('attendance.export'))->assertForbidden();

        $response = $this->actingAs($this->admin)->get(route('attendance.export', ['month' => '2026-10']));
        $response->assertOk();
        $this->assertStringContainsString($this->member->name, $response->streamedContent());
    }

    public function test_only_managers_manage_holidays(): void
    {
        Livewire::actingAs($this->member)->test(AttendancePage::class, ['tab' => 'holidays'])
            ->call('editHoliday')->assertForbidden();

        Livewire::actingAs($this->admin)->test(AttendancePage::class, ['tab' => 'holidays'])
            ->call('editHoliday')
            ->set('hDate', '2026-10-14')->set('hName', 'Company day')->set('hType', 'company')
            ->call('saveHoliday')->assertHasNoErrors();

        $this->assertFalse(app(WorkCalendar::class)->isWorkingDay(Carbon::parse('2026-10-14')));
    }

    public function test_leave_types_are_managed_by_managers(): void
    {
        Livewire::actingAs($this->member)->test(LeavePage::class)->call('editType')->assertForbidden();

        Livewire::actingAs($this->admin)->test(LeavePage::class, ['tab' => 'types'])
            ->call('editType', $this->casual->id)
            ->set('tDays', '15')
            ->call('saveType')->assertHasNoErrors();

        $this->assertEquals(15, $this->casual->fresh()->days_per_year);
    }

    // ── Super admin ────────────────────────────────────────────

    public function test_super_admin_is_not_tracked(): void
    {
        $this->actingAs($this->superAdmin)->get(route('dashboard'))->assertOk();
        $this->assertDatabaseMissing('attendance_records', ['user_id' => $this->superAdmin->id]);

        Livewire::actingAs($this->superAdmin)->test(AttendancePage::class)->assertSet('tab', 'today')
            ->assertDontSee('My attendance');
        Livewire::actingAs($this->superAdmin)->test(LeavePage::class)->assertSet('tab', 'approvals')
            ->call('openApply')->assertForbidden();

        $csv = $this->actingAs($this->superAdmin)->get(route('attendance.export'))->streamedContent();
        $this->assertStringContainsString($this->admin->name, $csv);
        $this->assertStringNotContainsString($this->superAdmin->name, $csv);
    }

    public function test_super_admin_adds_edits_and_deletes_anyones_leave(): void
    {
        $page = Livewire::actingAs($this->superAdmin)->test(LeavePage::class)
            ->call('openEntry')
            ->set('forUserId', (string) $this->admin->id)
            ->set('start_date', '2026-09-01')->set('end_date', '2026-09-02') // further back than employees may apply
            ->set('reason', 'Was unwell')
            ->call('saveEntry')->assertHasNoErrors();

        $leave = LeaveRequest::where('user_id', $this->admin->id)->firstOrFail();
        $this->assertSame('approved', $leave->status);
        $this->assertSame($this->superAdmin->id, $leave->reviewed_by);
        $this->assertSame(1, $this->admin->notifications()->count());

        // Editing gives the request's own days back before checking the balance.
        $this->casual->update(['days_per_year' => 3]);
        $page->call('openEntry', $leave->id)->set('end_date', '2026-09-03')->call('saveEntry')->assertHasNoErrors();
        $this->assertEquals(3, $leave->fresh()->days);

        $page->call('deleteLeave', $leave->id);
        $this->assertModelMissing($leave);
    }

    public function test_super_admin_cannot_add_leave_for_themselves_and_others_cannot_administer(): void
    {
        Livewire::actingAs($this->superAdmin)->test(LeavePage::class)
            ->call('openEntry')->set('forUserId', (string) $this->superAdmin->id)
            ->set('reason', 'Holiday')->call('saveEntry')->assertHasErrors('forUserId');

        $leave = $this->applyFor($this->member, '2026-10-07');
        Livewire::actingAs($this->admin)->test(LeavePage::class)->call('openEntry')->assertForbidden();
        Livewire::actingAs($this->admin)->test(LeavePage::class)->call('deleteLeave', $leave->id)->assertForbidden();
    }

    public function test_super_admin_corrects_admin_attendance(): void
    {
        Livewire::actingAs($this->superAdmin)->test(AttendancePage::class, ['tab' => 'month'])
            ->call('openCorrection', $this->admin->id, '2026-10-01')
            ->set('corrStatus', 'absent')->set('corrNote', 'Did not work')
            ->call('saveCorrection')->assertHasNoErrors();

        $this->assertDatabaseHas('attendance_records', ['user_id' => $this->admin->id, 'status' => 'absent', 'source' => 'manual']);
    }

    // ── QA regressions ─────────────────────────────────────────

    public function test_background_polls_never_check_anyone_in(): void
    {
        $isPoll = fn (array $calls, array $updates = []) => AttendanceService::isBackgroundPoll(
            Request::create('/x', 'POST', ['components' => [['updates' => $updates, 'calls' => $calls]]], server: ['HTTP_X_LIVEWIRE' => '1']));

        $this->assertTrue($isPoll([['method' => '$refresh']]));
        $this->assertTrue($isPoll([['method' => 'check']]));
        $this->assertFalse($isPoll([['method' => 'send']]));
        $this->assertFalse($isPoll([], ['body' => 'hi']));
        $this->assertFalse(AttendanceService::isBackgroundPoll(Request::create('/dashboard')));

        app(AttendanceService::class)->recordActivity($this->member, backgroundPoll: true);
        $this->assertDatabaseMissing('attendance_records', ['user_id' => $this->member->id]);
    }

    public function test_month_links_work_on_the_31st(): void
    {
        $this->travelTo(Carbon::parse('2026-10-31 06:00:00', 'UTC'));

        Livewire::actingAs($this->admin)->test(AttendancePage::class, ['tab' => 'month'])
            ->call('shiftMonth', 1)->assertSet('month', '2026-11')
            ->assertViewHas('monthStart', fn ($m) => $m->format('Y-m-d') === '2026-11-01');
    }

    public function test_today_is_not_absent_for_people_west_of_the_server(): void
    {
        // 10:00 in Kerala = 05:30 in London: the London user simply isn't in yet.
        $this->travelTo(Carbon::parse('2026-10-06 04:30:00', 'UTC'));
        $london = User::factory()->create(['timezone' => 'Europe/London', 'created_at' => '2026-09-01']);
        AttendanceRecord::factory()->for($this->admin)->create(['date' => '2026-10-01']);

        $row = app(AttendanceService::class)->month(collect([$london]), Carbon::parse('2026-10-01'))->first();

        $this->assertSame('·', $row['days']['2026-10-06']['code']);
        $this->assertSame('A', $row['days']['2026-10-05']['code']);
    }

    public function test_leave_across_new_year_is_charged_to_each_year(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 04:00:00', 'UTC'));
        $this->casual->update(['days_per_year' => 3]);

        // 30–31 Dec 2026 (Wed, Thu) + 1–4 Jan 2027 (Fri, Sat, Mon; Sun off).
        $request = $this->applyFor($this->member, '2026-12-30', '2027-01-04');

        $this->assertEquals(2, $this->leave()->balance($this->member, $this->casual, 2026)['pending']);
        $this->assertEquals(3, $this->leave()->balance($this->member, $this->casual, 2027)['pending']);
        $this->assertSame((float) $request->days, 2.0 + 3.0);
    }

    public function test_deactivated_employees_leave_stays_editable(): void
    {
        $leave = $this->applyFor($this->member, '2026-10-07');
        $this->member->update(['is_active' => false]);

        Livewire::actingAs($this->superAdmin)->test(LeavePage::class)
            ->call('openEntry', $leave->id)->set('entryStatus', 'cancelled')
            ->call('saveEntry')->assertHasNoErrors();

        $this->assertSame('cancelled', $leave->fresh()->status);
    }

    public function test_csv_has_day_columns_even_with_nobody_tracked(): void
    {
        User::whereKeyNot($this->superAdmin->id)->update(['is_active' => false]);

        $csv = $this->actingAs($this->superAdmin)->get(route('attendance.export', ['month' => '2026-02']))->streamedContent();

        $this->assertStringContainsString('28 Sat', strtok($csv, "\n"));
    }
}
