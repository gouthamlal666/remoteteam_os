<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Livewire\Projects\Index as Projects;
use App\Livewire\Tasks\Show;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskExtrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_on_hold_tasks_are_not_overdue_and_can_be_resumed(): void
    {
        $member = User::factory()->create();
        $task = Task::factory()->create(['status' => TaskStatus::OnHold, 'deadline' => today()->subWeek()]);
        $task->assignees()->attach($member->id);
        $task->project->members()->syncWithoutDetaching([$member->id]);

        $this->assertFalse($task->fresh()->isOverdue());
        $this->assertSame(0, Task::overdue()->count());

        Livewire::actingAs($member)->test(Show::class, ['task' => $task])
            ->assertSee('Resume working')
            ->call('start');

        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_admins_can_delete_any_project_members_cannot(): void
    {
        $super = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $super->id]);
        $project->members()->attach($member->id);

        $this->assertFalse($member->can('delete', $project));
        $this->assertTrue($admin->can('delete', $project));

        Livewire::actingAs($admin)->test(Projects::class)->call('deleteProject', $project->id);
        $this->assertModelMissing($project);
    }

    public function test_repeat_creates_a_fresh_copy_with_new_dates(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $left = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $admin->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id, 'title' => 'Weekly poster', 'description' => 'Two posters and a reel',
            'status' => TaskStatus::Completed, 'start_date' => '2026-10-26', 'deadline' => '2026-10-30', 'estimated_hours' => 3,
        ]);
        $task->assignees()->attach([$member->id, $left->id]);
        $left->update(['is_active' => false]);
        $task->checklistItems()->create(['title' => 'Design', 'position' => 1, 'is_done' => true]);
        $task->comments()->create(['user_id' => $admin->id, 'body' => 'Looks good', 'type' => 'comment']);

        $page = Livewire::actingAs($admin)->test(Show::class, ['task' => $task])
            ->call('openRepeat')
            ->assertSet('repeatStart', '2026-11-02')->assertSet('repeatDeadline', '2026-11-06')
            ->call('shiftRepeat', 'month')
            ->assertSet('repeatStart', '2026-11-26')
            ->set('repeatStart', '2026-11-09')->set('repeatDeadline', '2026-11-13')
            ->call('repeat');

        $copy = Task::whereKeyNot($task->id)->firstOrFail();
        $page->assertRedirect(route('tasks.show', $copy));

        $this->assertSame('Weekly poster', $copy->title);
        $this->assertSame('Two posters and a reel', $copy->description);
        $this->assertSame(TaskStatus::Todo, $copy->status);
        $this->assertSame('2026-11-09', $copy->start_date->toDateString());
        $this->assertSame('2026-11-13', $copy->deadline->toDateString());
        $this->assertEquals([$member->id], $copy->assignees->pluck('id')->all());
        $this->assertFalse((bool) $copy->checklistItems()->first()->is_done);
        $this->assertSame(0, $copy->comments()->count());
        $this->assertSame(1, $member->notifications()->count());
    }

    public function test_repeat_validates_dates_and_needs_permission(): void
    {
        $member = User::factory()->create();
        $task = Task::factory()->create();
        $task->assignees()->attach($member->id);
        $task->project->members()->syncWithoutDetaching([$member->id]);

        Livewire::actingAs($member)->test(Show::class, ['task' => $task])->call('openRepeat')->assertForbidden();

        $admin = User::factory()->admin()->create();
        Livewire::actingAs($admin)->test(Show::class, ['task' => $task])
            ->call('openRepeat')
            ->set('repeatStart', '2026-11-10')->set('repeatDeadline', '2026-11-09')
            ->call('repeat')->assertHasErrors('repeatDeadline');
    }
}
