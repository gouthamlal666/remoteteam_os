<?php

namespace Tests\Feature;

use App\Livewire\Departments\Index;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_can_open_the_main_pages(): void
    {
        $this->seed();

        foreach (['sijojorge@gmail.com', 'mebijose@admin.co.uk', 'gouthamlal@developer.co.uk'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $project = Project::visibleTo($user)->firstOrFail();
            $task = Task::visibleTo($user)->firstOrFail();

            $this->actingAs($user);
            $urls = $user->isManager()
                ? ['/reports?tab=team', '/reports?tab=weekly', '/reports?tab=performance']
                : [];
            foreach ([...$urls, '/dashboard', '/projects', '/tasks', '/activity', '/chat', '/meetings', '/meetings?view=calendar', '/meetings?view=history', '/reports', "/projects/{$project->id}", "/tasks/{$task->id}", "/people/{$user->id}"] as $url) {
                $this->get($url)->assertOk();
            }
        }
    }

    public function test_members_cannot_open_management_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/people')->assertForbidden();
        $this->get('/departments')->assertForbidden();
    }

    public function test_only_super_admin_manages_departments(): void
    {
        $department = Department::factory()->create();

        $this->assertTrue(User::factory()->superAdmin()->create()->can('update', $department));
        $this->assertFalse(User::factory()->admin()->create()->can('update', $department));
    }

    public function test_members_only_see_projects_they_belong_to(): void
    {
        $member = User::factory()->create();
        $mine = Project::factory()->create();
        $mine->members()->attach($member);
        $other = Project::factory()->create();

        $this->actingAs($member);
        $this->get("/projects/{$mine->id}")->assertOk();
        $this->get("/projects/{$other->id}")->assertForbidden();

        $this->assertEquals([$mine->id], Project::visibleTo($member)->pluck('id')->all());
    }

    public function test_admin_sees_department_projects_but_not_others(): void
    {
        $department = Department::factory()->create();
        $admin = User::factory()->admin()->create(['department_id' => $department->id]);
        $inDept = Project::factory()->create(['department_id' => $department->id]);
        $elsewhere = Project::factory()->create();

        $this->assertTrue($admin->can('update', $inDept));
        $this->assertFalse($admin->can('view', $elsewhere));
    }

    public function test_admin_can_only_manage_members_of_own_department(): void
    {
        $dept = Department::factory()->create();
        $admin = User::factory()->admin()->create(['department_id' => $dept->id]);
        $ownMember = User::factory()->create(['department_id' => $dept->id]);
        $otherMember = User::factory()->create(['department_id' => Department::factory()->create()->id]);
        $otherAdmin = User::factory()->admin()->create(['department_id' => $dept->id]);

        $this->assertTrue($admin->can('update', $ownMember));
        $this->assertFalse($admin->can('update', $otherMember));
        $this->assertFalse($admin->can('update', $otherAdmin));
    }

    public function test_attachment_download_requires_task_access(): void
    {
        $task = Task::factory()->create();
        $attachment = $task->attachments()->create([
            'user_id' => $task->created_by, 'path' => 'tasks/x.txt', 'original_name' => 'x.txt', 'size' => 1,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('attachments.download', $attachment))
            ->assertForbidden();
    }

    public function test_assigning_a_manager_keeps_their_own_department_and_widens_their_scope(): void
    {
        $super = User::factory()->superAdmin()->create();
        [$dev, $design, $video] = Department::factory()->count(3)->create();
        $admin = User::factory()->admin()->create(['department_id' => $dev->id]);
        $designer = User::factory()->create(['department_id' => $design->id]);
        $designProject = Project::factory()->create(['department_id' => $design->id]);

        foreach ([$design, $video] as $department) {
            Livewire::actingAs($super)->test(Index::class)
                ->call('edit', $department->id)
                ->set('manager_id', $admin->id)
                ->call('save')
                ->assertHasNoErrors();
        }

        // Their own department is untouched…
        $this->assertSame($dev->id, $admin->fresh()->department_id);
        // …and they can now manage people and projects in the departments they manage.
        $this->assertTrue($admin->fresh()->can('update', $designer));
        $this->assertTrue($admin->fresh()->can('update', $designProject));
    }
}
