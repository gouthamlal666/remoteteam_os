<?php

namespace Tests\Feature;

use App\Livewire\Topbar;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Alert;
use App\Services\ChatService;
use App\Services\MeetingService;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function alertOfType(string $type): \Closure
    {
        return fn (Alert $n) => $n->type === $type;
    }

    public function test_task_lifecycle_notifies_the_right_people(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $dev = User::factory()->create(['name' => 'Rahul Menon']);
        $project = Project::factory()->create(['owner_id' => $admin->id]);
        $tasks = app(TaskService::class);

        $task = $tasks->create($admin, ['project_id' => $project->id, 'title' => 'API auth'], [$dev->id]);
        Notification::assertSentTo($dev, Alert::class, $this->alertOfType('task.assigned'));
        Notification::assertNotSentTo($admin, Alert::class);

        $tasks->submit($dev, $task->fresh(), 'done');
        Notification::assertSentTo($admin, Alert::class, $this->alertOfType('task.submitted'));

        $tasks->requestRevision($admin, $task->fresh(), 'add tests');
        Notification::assertSentTo($dev, Alert::class, $this->alertOfType('task.revision'));

        $tasks->addComment($admin, $task->fresh(), 'ping @rahul.menon');
        Notification::assertSentTo($dev, Alert::class, $this->alertOfType('task.mentioned'));
    }

    public function test_reassigning_only_notifies_new_assignees(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        [$a, $b] = User::factory()->count(2)->create();
        $task = app(TaskService::class)->create($admin, ['project_id' => Project::factory()->create(['owner_id' => $admin->id])->id, 'title' => 'T'], [$a->id]);

        Notification::fake();
        app(TaskService::class)->update($admin, $task, ['title' => 'T2'], [$a->id, $b->id]);

        Notification::assertSentTo($b, Alert::class);
        Notification::assertNotSentTo($a, Alert::class);
    }

    public function test_email_only_for_important_alerts_and_respects_preference(): void
    {
        $user = User::factory()->create(['email_notifications' => true]);
        $optedOut = User::factory()->create(['email_notifications' => false]);
        $important = new Alert('task.assigned', 't', 'b', '/', mail: true);

        $this->assertSame(['database', 'mail'], $important->via($user));
        $this->assertSame(['database'], $important->via($optedOut));
        $this->assertSame(['database'], (new Alert('chat.direct', 't', 'b', '/'))->via($user));
    }

    public function test_direct_messages_notify_once_until_read(): void
    {
        [$a, $b] = User::factory()->count(2)->create();
        $chat = app(ChatService::class);
        $dm = $chat->directBetween($a, $b);

        $chat->send($a, $dm, 'hi');
        $chat->send($a, $dm, 'are you there?');
        $this->assertSame(1, $b->notifications()->count());

        $chat->markRead($b, $dm);
        $this->travel(1)->seconds();
        $chat->send($a, $dm, 'ping');
        $this->assertSame(2, $b->notifications()->count());
    }

    public function test_meeting_invites_and_reminders_are_sent_once(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $dev = User::factory()->create();

        $meeting = app(MeetingService::class)->save($admin, null, ['title' => 'Standup', 'starts_at' => now()->addMinutes(10), 'duration_minutes' => 15], [$dev->id]);
        Notification::assertSentTo($dev, Alert::class, $this->alertOfType('meeting.invited'));

        Notification::fake();
        $this->artisan('reminders:send')->assertSuccessful();
        $this->artisan('reminders:send')->assertSuccessful();
        Notification::assertSentToTimes($dev, Alert::class, 1);
        $this->assertNotNull($meeting->fresh()->reminded_at);
    }

    public function test_due_tomorrow_reminder(): void
    {
        Notification::fake();
        $dev = User::factory()->create();
        $task = Task::factory()->create(['deadline' => today()->addDay()]);
        $task->assignees()->attach($dev);
        Task::factory()->create(['deadline' => today()->addDays(5)])->assignees()->attach($dev);

        $this->artisan('reminders:send');
        $this->artisan('reminders:send');

        Notification::assertSentToTimes($dev, Alert::class, 1);
    }

    public function test_bell_opens_and_marks_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new Alert('x', 'Hello', 'World', route('dashboard')));
        $id = $user->notifications()->first()->id;

        Livewire::actingAs($user)->test(Topbar::class)
            ->assertSee('Hello')
            ->call('open', $id)
            ->assertRedirect(route('dashboard'));

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_users_cannot_open_someone_elses_notification(): void
    {
        $owner = User::factory()->create();
        $owner->notify(new Alert('x', 'Private', 'b', '/'));

        Livewire::actingAs(User::factory()->create())->test(Topbar::class)
            ->call('open', $owner->notifications()->first()->id)
            ->assertNotFound();
    }
}
