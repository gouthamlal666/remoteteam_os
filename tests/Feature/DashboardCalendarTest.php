<?php

namespace Tests\Feature;

use App\Livewire\DashboardCalendar;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_calendar_instead_of_recent_activity(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Recent activity')
            ->assertSee('Open meetings calendar');
    }

    public function test_agenda_lists_todays_meetings_and_deadlines(): void
    {
        $member = User::factory()->create();
        $meeting = Meeting::create(['organizer_id' => User::factory()->admin()->create()->id, 'title' => 'Daily standup', 'starts_at' => now()->addMinutes(20)]);
        $meeting->participants()->attach($member);
        $task = Task::factory()->create(['title' => 'Ship login page', 'deadline' => today()]);
        $task->assignees()->attach($member);
        Task::factory()->create(['title' => 'Someone else task', 'deadline' => today()]);

        Livewire::actingAs($member)->test(DashboardCalendar::class)
            ->assertSee('Today')
            ->assertSee('Daily standup')
            ->assertSee('in 20 min')
            ->assertSee('Ship login page')
            ->assertDontSee('Someone else task');
    }

    public function test_selecting_days_and_months(): void
    {
        $member = User::factory()->create();
        $task = Task::factory()->create(['title' => 'Next month deliverable', 'deadline' => today()->addMonthNoOverflow()->startOfMonth()->addDays(9)]);
        $task->assignees()->attach($member);

        Livewire::actingAs($member)->test(DashboardCalendar::class)
            ->assertDontSee('Next month deliverable')
            ->call('shiftMonth', 1)
            ->call('select', $task->deadline->toDateString())
            ->assertSee('Next month deliverable')
            ->call('goToday')
            ->assertSet('selected', today()->toDateString());
    }

    public function test_managers_see_deadlines_across_their_projects(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['owner_id' => $admin->id]);
        Task::factory()->create(['project_id' => $project->id, 'title' => 'Team deadline', 'deadline' => today()]);

        Livewire::actingAs($admin)->test(DashboardCalendar::class)->assertSee('Team deadline');
    }
}
