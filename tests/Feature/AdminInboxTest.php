<?php

namespace Tests\Feature;

use App\Livewire\SystemSettings;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Alert;
use App\Services\Notifier;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AdminInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_emails_go_to_the_shared_inbox_and_members_to_their_own(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'mebi@example.com']);
        $member = User::factory()->create(['email' => 'goutham@example.com']);

        $this->assertSame('mebi@example.com', $admin->routeNotificationForMail());

        Settings::set(['notifications.admin_inbox' => 'admin@geodesignmedia.co.uk']);

        $this->assertSame('admin@geodesignmedia.co.uk', $admin->fresh()->routeNotificationForMail());
        $this->assertSame('goutham@example.com', $member->routeNotificationForMail());
    }

    public function test_the_inbox_gets_one_email_even_when_several_admins_are_notified(): void
    {
        Notification::fake();
        Settings::set(['notifications.admin_inbox' => 'admin@geodesignmedia.co.uk']);
        $super = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $task = Task::factory()->create(['created_by' => $admin->id, 'project_id' => Project::factory()->create(['owner_id' => $super->id])->id]);

        app(Notifier::class)->taskSubmitted($task, $member);

        $mailed = collect([$super, $admin])->filter(fn ($u) => Notification::sent($u, Alert::class)
            ->contains(fn ($n) => in_array('mail', $n->via($u), true)));
        $this->assertCount(1, $mailed, 'exactly one email to the shared inbox');
        Notification::assertSentTo([$super, $admin], Alert::class); // both still see it in the app
    }

    public function test_a_failing_email_does_not_break_the_action_or_other_people(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        config(['mail.default' => 'failing']);
        config(['mail.mailers.failing' => ['transport' => 'smtp', 'host' => '256.0.0.1', 'port' => 1, 'timeout' => 1]]);

        $task = Task::factory()->create();
        app(Notifier::class)->taskAssigned($task, [$member, $other], User::factory()->admin()->create());

        $this->assertSame(1, $member->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
    }

    public function test_super_admin_sets_the_inbox_in_system_settings(): void
    {
        $super = User::factory()->superAdmin()->create();

        Livewire::actingAs($super)->test(SystemSettings::class, ['tab' => 'notifications'])
            ->set('admin_inbox', 'not-an-email')->call('saveNotifications')->assertHasErrors('admin_inbox')
            ->set('admin_inbox', 'admin@geodesignmedia.co.uk')->call('saveNotifications')->assertHasNoErrors();

        $this->assertSame('admin@geodesignmedia.co.uk', Settings::adminInbox());
    }
}
