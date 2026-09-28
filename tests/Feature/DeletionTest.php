<?php

namespace Tests\Feature;

use App\Livewire\Chat\Index as Chat;
use App\Livewire\Meetings\Show as MeetingShow;
use App\Livewire\Notifications;
use App\Livewire\Projects\Show;
use App\Livewire\Reports\Index as Reports;
use App\Livewire\Tasks\Index;
use App\Livewire\Tasks\Show as TaskShow;
use App\Livewire\Users\Index as People;
use App\Models\Conversation;
use App\Models\DailyReport;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Alert;
use App\Services\ChatService;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_deletes_a_person_and_their_work_is_transferred(): void
    {
        $super = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['owner_id' => $admin->id]);
        $task = app(TaskService::class)->create($admin, ['project_id' => $project->id, 'title' => 'Keep me'], []);
        app(TaskService::class)->addComment($admin, $task, 'Important context');
        $meeting = Meeting::create(['organizer_id' => $admin->id, 'title' => 'Sync', 'starts_at' => now()->addDay()]);

        Livewire::actingAs($super)->test(People::class)->call('delete', $admin->id)->assertHasNoErrors();

        $this->assertModelMissing($admin);
        $this->assertSame($super->id, $project->fresh()->owner_id);
        $this->assertSame($super->id, $task->fresh()->created_by);
        $this->assertSame($super->id, $meeting->fresh()->organizer_id);
        $comment = $task->comments()->first();
        $this->assertSame('Important context', $comment->body);
        $this->assertSame('Deleted user', $comment->user->name);
    }

    public function test_who_can_delete_people(): void
    {
        $super = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->assertFalse($super->can('delete', $super));
        $this->assertTrue($super->can('delete', $member));
        $this->assertTrue($admin->can('delete', $member));
        $this->assertFalse($admin->can('delete', $otherAdmin));
        $this->assertFalse($admin->can('delete', $super));
        $this->assertFalse($member->can('delete', User::factory()->create()));

        Livewire::actingAs($admin)->test(People::class)->call('delete', $member->id)->assertHasNoErrors();
        $this->assertModelMissing($member);
    }

    public function test_meeting_delete_notifies_upcoming_participants(): void
    {
        $super = User::factory()->superAdmin()->create();
        $dev = User::factory()->create();
        $meeting = Meeting::create(['organizer_id' => User::factory()->admin()->create()->id, 'title' => 'Demo', 'starts_at' => now()->addDay()]);
        $meeting->participants()->attach($dev);

        Livewire::actingAs($super)->test(MeetingShow::class, ['meeting' => $meeting])->call('delete')->assertRedirect(route('meetings.index'));

        $this->assertModelMissing($meeting);
        $this->assertSame(route('meetings.index'), $dev->notifications()->first()->data['url']);
        Livewire::actingAs($dev);
    }

    public function test_channel_owner_deletes_channel_and_moderates_messages(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $chat = app(ChatService::class);
        $channel = $chat->createChannel($admin, 'Team', null, [$member->id]);
        $spam = $chat->send($member, $channel, 'spam');

        Livewire::actingAs($member)->test(Chat::class, ['conversation' => $channel])->call('deleteChannel')->assertForbidden();

        Livewire::actingAs($admin)->test(Chat::class, ['conversation' => $channel])
            ->call('deleteMessage', $spam->id)
            ->call('deleteChannel');

        $this->assertModelMissing($spam);
        $this->assertSame(0, Conversation::count());
    }

    public function test_comment_report_and_notification_deletes(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $task = Task::factory()->create(['project_id' => Project::factory()->create(['owner_id' => $admin->id])->id]);
        $task->project->members()->attach($member);
        $mine = $task->comments()->create(['user_id' => $member->id, 'body' => 'mine']);
        $theirs = $task->comments()->create(['user_id' => $admin->id, 'body' => 'theirs']);

        Livewire::actingAs($member)->test(TaskShow::class, ['task' => $task])
            ->call('deleteComment', $mine->id)
            ->call('deleteComment', $theirs->id)
            ->assertForbidden();
        $this->assertModelMissing($mine);

        $report = DailyReport::create(['user_id' => $member->id, 'report_date' => today(), 'completed' => 'x', 'hours_worked' => 8]);
        Livewire::actingAs(User::factory()->create())->test(Reports::class)->call('deleteReport', $report->id)->assertForbidden();
        Livewire::actingAs(User::factory()->superAdmin()->create())->test(Reports::class)->call('deleteReport', $report->id);
        $this->assertModelMissing($report);

        $member->notify(new Alert('x', 'a', 'b', '/'));
        $member->notify(new Alert('x', 'c', 'd', '/'));
        $member->notifications()->first()->markAsRead();
        Livewire::actingAs($member)->test(Notifications::class)->call('clearRead');
        $this->assertSame(1, $member->notifications()->count());
    }

    public function test_super_admin_deletes_from_lists_and_inside_projects(): void
    {
        $super = User::factory()->superAdmin()->create();
        $dev = User::factory()->create();
        $project = Project::factory()->create();
        $project->members()->attach($dev);
        [$a, $b, $c] = Task::factory()->count(3)->create(['project_id' => $project->id]);
        $a->assignees()->attach($dev);

        // Remove a member from inside the project: also unassigns them.
        Livewire::actingAs($super)->test(Show::class, ['project' => $project])
            ->call('removeMember', $dev->id)
            ->call('deleteTask', $c->id);
        $this->assertFalse($project->members()->whereKey($dev->id)->exists());
        $this->assertFalse($a->assignees()->whereKey($dev->id)->exists());
        $this->assertModelMissing($c);

        // Bulk delete from the tasks list.
        Livewire::actingAs($super)->test(Index::class)
            ->set('selected', [(string) $a->id, (string) $b->id])
            ->call('deleteSelected')
            ->assertSet('selected', []);
        $this->assertSame(0, Task::count());

        // Delete a project from the projects list.
        Livewire::actingAs($super)->test(\App\Livewire\Projects\Index::class)->call('deleteProject', $project->id);
        $this->assertModelMissing($project);
    }

    public function test_members_cannot_use_list_deletes(): void
    {
        $member = User::factory()->create();
        $project = Project::factory()->create();
        $project->members()->attach($member);
        $task = Task::factory()->create(['project_id' => $project->id]);

        Livewire::actingAs($member)->test(\App\Livewire\Projects\Index::class)->call('deleteProject', $project->id)->assertForbidden();
        Livewire::actingAs($member)->test(Index::class)->call('deleteTask', $task->id)->assertForbidden();

        // Bulk delete silently skips anything the user may not delete.
        Livewire::actingAs($member)->test(Index::class)->set('selected', [(string) $task->id])->call('deleteSelected');
        $this->assertModelExists($task);
    }

    public function test_meeting_list_delete_and_participant_removal(): void
    {
        $super = User::factory()->superAdmin()->create();
        $organizer = User::factory()->admin()->create();
        $dev = User::factory()->create();
        $meeting = Meeting::create(['organizer_id' => $organizer->id, 'title' => 'Sync', 'starts_at' => now()->addDay()]);
        $meeting->participants()->attach([$organizer->id, $dev->id]);

        Livewire::actingAs($super)->test(MeetingShow::class, ['meeting' => $meeting])
            ->call('removeParticipant', $dev->id)
            ->call('removeParticipant', $organizer->id)
            ->assertStatus(422);
        $this->assertSame([$organizer->id], $meeting->participants()->pluck('users.id')->all());

        Livewire::actingAs($super)->test(\App\Livewire\Meetings\Index::class)->call('deleteMeeting', $meeting->id);
        $this->assertModelMissing($meeting);
    }

    public function test_person_can_be_deleted_from_their_profile(): void
    {
        $super = User::factory()->superAdmin()->create();
        $member = User::factory()->create();

        Livewire::actingAs($super)->test(\App\Livewire\Users\Show::class, ['user' => $member])
            ->assertSee('Delete person')
            ->call('delete')
            ->assertRedirect(route('users.index'));

        $this->assertModelMissing($member);
        Livewire::actingAs($super)->test(\App\Livewire\Users\Show::class, ['user' => $super])->assertDontSee('Delete person');
    }
}
