<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Livewire\Reports\Index;
use App\Models\DailyReport;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Services\PerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_submits_and_updates_daily_report(): void
    {
        $member = User::factory()->create();

        $lw = Livewire::actingAs($member)->test(Index::class)
            ->set('completed', 'Finished login API')
            ->set('hoursWorked', '7.5')
            ->call('submitReport')
            ->assertHasNoErrors();

        $lw->set('blockers', 'Need SMTP creds')->call('submitReport');

        $this->assertSame(1, DailyReport::count());
        $this->assertSame('Need SMTP creds', DailyReport::first()->blockers);
    }

    public function test_reports_cannot_be_in_the_future(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Index::class)
            ->set('reportDate', today()->addDay()->toDateString())
            ->assertSet('reportDate', today()->toDateString());
    }

    public function test_members_only_get_their_own_tab(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Index::class, ['tab' => 'team'])
            ->assertSet('tab', 'mine')
            ->assertDontSee('Team daily');
    }

    public function test_admin_team_view_covers_members_in_every_department(): void
    {
        $admin = User::factory()->admin()->create(['department_id' => Department::factory()->create()->id]);
        User::factory()->create(['department_id' => Department::factory()->create()->id, 'name' => 'Elsewhere Person']);
        User::factory()->admin()->create(['name' => 'Another Admin']);

        Livewire::actingAs($admin)->test(Index::class)
            ->set('tab', 'team')
            ->assertSee('Elsewhere Person')
            ->assertDontSee('Another Admin');
    }

    public function test_weekly_summary_counts_reports_hours_and_completed_tasks(): void
    {
        $monday = Carbon::parse('last monday');
        $user = User::factory()->create();
        DailyReport::create(['user_id' => $user->id, 'report_date' => $monday, 'completed' => 'x', 'hours_worked' => 8]);
        DailyReport::create(['user_id' => $user->id, 'report_date' => $monday->copy()->addDay(), 'completed' => 'y', 'hours_worked' => 6.5, 'blockers' => 'VPN down']);
        $task = Task::factory()->create(['status' => TaskStatus::Completed, 'completed_at' => $monday->copy()->addDays(2), 'deadline' => $monday->copy()->addDays(3)]);
        $task->assignees()->attach($user);

        $row = app(PerformanceService::class)->weekly(collect([$user]), $monday)->first();

        $this->assertSame(2, $row['days_reported']);
        $this->assertSame(14.5, $row['hours']);
        $this->assertSame(1, $row['completed']->count());
        $this->assertSame(1, $row['completed_on_time']);
        $this->assertSame(1, $row['blockers']->count());
    }
}
