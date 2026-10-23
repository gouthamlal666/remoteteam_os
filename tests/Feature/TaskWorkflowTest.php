<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Livewire\Tasks\Show;
use App\Livewire\Tasks\TaskEditor;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->member = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $this->admin->id]);
        $this->task = Task::factory()->create(['project_id' => $project->id]);
        $this->task->assignees()->attach($this->member);
        $project->members()->attach($this->member);
    }

    public function test_admin_creates_task_and_assignee_joins_project(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create(['owner_id' => $this->admin->id]);
        $newcomer = User::factory()->create();

        Livewire::actingAs($this->admin)->test(TaskEditor::class)
            ->call('openEditor', projectId: $project->id)
            ->set('title', 'Build Laravel API Authentication')
            ->set('priority', 'high')
            ->set('deadline', today()->addWeek()->toDateString())
            ->set('assignee_ids', [(string) $newcomer->id])
            ->set('files', [UploadedFile::fake()->create('requirements.pdf', 100)])
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('task-saved');

        $task = Task::where('title', 'Build Laravel API Authentication')->firstOrFail();
        $this->assertTrue($task->assignees->contains($newcomer));
        $this->assertTrue($project->members()->whereKey($newcomer->id)->exists());
        $this->assertCount(1, $task->attachments);
        Storage::disk('local')->assertExists($task->attachments->first()->path);
        $this->assertDatabaseHas('activities', ['action' => 'task.created', 'subject_id' => $task->id]);
    }

    public function test_member_cannot_create_tasks(): void
    {
        Livewire::actingAs($this->member)->test(TaskEditor::class)
            ->call('openEditor', projectId: $this->task->project_id)
            ->assertForbidden();
    }

    public function test_full_submit_revision_approve_cycle(): void
    {
        Livewire::actingAs($this->member)->test(Show::class, ['task' => $this->task])
            ->call('start')
            ->call('openDialog', 'submit')
            ->set('note', 'Done, see PR #12')
            ->call('confirmDialog')
            ->assertHasNoErrors();

        $this->assertSame(TaskStatus::Review, $this->task->fresh()->status);

        // Member cannot approve their own work.
        Livewire::actingAs($this->member)->test(Show::class, ['task' => $this->task->fresh()])
            ->call('openDialog', 'approve')
            ->call('confirmDialog')
            ->assertForbidden();

        Livewire::actingAs($this->admin)->test(Show::class, ['task' => $this->task->fresh()])
            ->call('openDialog', 'revision')
            ->call('confirmDialog')
            ->assertHasErrors('note')
            ->set('note', 'Please add tests')
            ->call('confirmDialog');

        $task = $this->task->fresh();
        $this->assertSame(TaskStatus::Revision, $task->status);
        $this->assertSame(1, $task->revision_count);

        Livewire::actingAs($this->member)->test(Show::class, ['task' => $task])
            ->call('openDialog', 'submit')->set('note', 'Tests added')->call('confirmDialog');
        Livewire::actingAs($this->admin)->test(Show::class, ['task' => $task->fresh()])
            ->call('openDialog', 'approve')->call('confirmDialog');

        $task = $task->fresh();
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertSame(['submission', 'revision', 'submission', 'approval'], $task->comments()->orderBy('id')->pluck('type')->all());
    }

    public function test_non_assigned_member_on_project_can_view_and_comment_but_not_submit(): void
    {
        $teammate = User::factory()->create();
        $this->task->project->members()->attach($teammate);

        Livewire::actingAs($teammate)->test(Show::class, ['task' => $this->task])
            ->set('comment', 'Need help?')
            ->call('addComment')
            ->assertHasNoErrors()
            ->call('openDialog', 'submit')
            ->set('note', 'x')
            ->call('confirmDialog')
            ->assertForbidden();

        $this->assertDatabaseHas('task_comments', ['user_id' => $teammate->id, 'body' => 'Need help?']);
    }

    public function test_overdue_is_derived_from_deadline(): void
    {
        $late = Task::factory()->overdue()->create();
        $lateButDone = Task::factory()->overdue()->status(TaskStatus::Completed)->create();

        $this->assertTrue($late->isOverdue());
        $this->assertFalse($lateButDone->isOverdue());
        $this->assertEquals([$late->id], Task::overdue()->pluck('id')->all());
    }

    public function test_task_stats_are_computed_from_assigned_tasks(): void
    {
        $user = User::factory()->create();
        $onTime = Task::factory()->status(TaskStatus::Completed)->create(['deadline' => today()->addDay()]);
        $late = Task::factory()->status(TaskStatus::Completed)->create(['deadline' => today()->subDays(2)]);
        $open = Task::factory()->overdue()->create();
        $user->tasks()->attach([$onTime->id, $late->id, $open->id]);

        $stats = $user->taskStats();

        $this->assertSame(3, $stats['assigned']);
        $this->assertSame(2, $stats['completed']);
        $this->assertSame(1, $stats['overdue']);
        $this->assertSame(67, $stats['completion_rate']);
        $this->assertSame(50, $stats['on_time_rate']);
    }
}
