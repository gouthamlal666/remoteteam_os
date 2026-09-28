<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\CommandPalette;
use App\Livewire\Projects\Show as ProjectShow;
use App\Livewire\Settings;
use App\Livewire\Tasks\Show;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TimeTracker;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class WorkToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $dev;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->dev = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $this->admin->id]);
        $project->members()->attach($this->dev);
        $this->task = Task::factory()->create(['project_id' => $project->id, 'estimated_hours' => 2]);
        $this->task->assignees()->attach($this->dev);
    }

    public function test_timer_start_stop_and_one_running_at_a_time(): void
    {
        $tracker = app(TimeTracker::class);
        $other = Task::factory()->create();

        $tracker->start($this->dev, $this->task);
        $this->travel(25)->minutes();
        $tracker->start($this->dev, $other); // stops the first

        $this->assertSame(1, $this->dev->timeEntries()->running()->count());
        $this->assertSame(25, $this->task->timeEntries()->first()->minutes);
    }

    public function test_assignee_tracks_time_from_task_page_and_it_starts_the_task(): void
    {
        Livewire::actingAs($this->dev)->test(Show::class, ['task' => $this->task])
            ->call('startTimer')
            ->assertDispatched('timer-changed')
            ->set('logHours', '1.5')
            ->call('logTime')
            ->assertHasNoErrors();

        $this->assertSame(TaskStatus::InProgress, $this->task->fresh()->status);
        $this->assertSame(90, (int) $this->task->timeEntries()->whereNotNull('ended_at')->sum('minutes'));
    }

    public function test_project_members_who_are_not_assigned_cannot_track_or_edit_checklist(): void
    {
        $viewer = User::factory()->create();
        $this->task->project->members()->attach($viewer);

        Livewire::actingAs($viewer)->test(Show::class, ['task' => $this->task])->call('startTimer')->assertForbidden();
        Livewire::actingAs($viewer)->test(Show::class, ['task' => $this->task])->set('newItem', 'x')->call('addItem')->assertForbidden();
    }

    public function test_checklist_add_toggle_delete(): void
    {
        $lw = Livewire::actingAs($this->dev)->test(Show::class, ['task' => $this->task])
            ->set('newItem', 'Write tests')->call('addItem');

        $item = $this->task->checklistItems()->first();
        $lw->call('toggleItem', $item->id);
        $this->assertTrue($item->fresh()->is_done);
        $this->assertSame($this->dev->id, $item->fresh()->completed_by);

        $lw->call('deleteItem', $item->id);
        $this->assertSame(0, $this->task->checklistItems()->count());
    }

    public function test_board_drag_respects_permissions(): void
    {
        // Assignee: To Do → In Progress is fine, jumping to Completed is not.
        Livewire::actingAs($this->dev)->test(ProjectShow::class, ['project' => $this->task->project])
            ->call('moveTask', $this->task->id, 0, 'in_progress')
            ->call('moveTask', $this->task->id, 0, 'completed')
            ->assertDispatched('notify');
        $this->assertSame(TaskStatus::InProgress, $this->task->fresh()->status);

        // Manager can move anywhere; completing sets completed_at.
        Livewire::actingAs($this->admin)->test(ProjectShow::class, ['project' => $this->task->project])
            ->call('moveTask', $this->task->id, 0, 'completed');
        $this->assertSame(TaskStatus::Completed, $this->task->fresh()->status);
        $this->assertNotNull($this->task->fresh()->completed_at);
    }

    public function test_command_palette_only_finds_visible_tasks(): void
    {
        Task::factory()->create(['title' => 'Secret payroll migration']);
        Task::factory()->create(['project_id' => $this->task->project_id, 'title' => 'Payroll export']);

        Livewire::actingAs($this->dev)->test(CommandPalette::class)
            ->set('query', 'payroll')
            ->assertSee('Payroll export')
            ->assertDontSee('Secret payroll migration');
    }

    public function test_settings_status_timezone_and_password(): void
    {
        Livewire::actingAs($this->dev)->test(Settings::class)
            ->call('usePreset', 0)
            ->call('saveStatus')
            ->set('timezone', 'Mars/Olympus')
            ->call('saveProfile')
            ->assertHasErrors('timezone')
            ->set('timezone', 'Europe/Berlin')
            ->set('work_start', '09:00')
            ->set('work_end', '17:00')
            ->call('saveProfile')
            ->assertHasNoErrors()
            ->set('current_password', 'wrong')
            ->set('password', 'new-password-1')
            ->set('password_confirmation', 'new-password-1')
            ->call('changePassword')
            ->assertHasErrors('current_password')
            ->set('current_password', 'password')
            ->call('changePassword')
            ->assertHasNoErrors();

        $dev = $this->dev->fresh();
        $this->assertSame('📅 In a meeting', $dev->activeStatus());
        $this->assertSame('Europe/Berlin', $dev->timezone);
        $this->assertTrue(Hash::check('new-password-1', $dev->password));

        $this->travel(2)->hours();
        $this->assertNull($dev->fresh()->activeStatus()); // preset expired after 1 hour
    }

    public function test_forgot_password_sends_reset_link_only_to_active_users(): void
    {
        Notification::fake();
        $inactive = User::factory()->inactive()->create();

        Livewire::test(ForgotPassword::class)->set('email', $this->dev->email)->call('sendLink')->assertSet('sent', true);
        Livewire::test(ForgotPassword::class)->set('email', $inactive->email)->call('sendLink')->assertSet('sent', true);

        Notification::assertSentTo($this->dev, ResetPassword::class);
        Notification::assertNotSentTo($inactive, ResetPassword::class);
    }

    public function test_active_check_also_runs_on_livewire_requests(): void
    {
        // Persistent middleware applies to real HTTP Livewire updates; assert it is registered.
        $this->assertContains(EnsureUserIsActive::class, Livewire::getPersistentMiddleware());
    }
}
