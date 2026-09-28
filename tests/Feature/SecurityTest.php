<?php

namespace Tests\Feature;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Chat\Index as Chat;
use App\Livewire\Meetings\Index as Meetings;
use App\Livewire\Projects\Index as Projects;
use App\Livewire\Settings;
use App\Livewire\Users\Index as People;
use App\Models\Department;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_preview_cannot_reveal_messages_from_other_conversations(): void
    {
        [$alice, $bob, $mallory] = User::factory()->count(3)->create();
        $chat = app(ChatService::class);
        $secret = $chat->send($alice, $chat->directBetween($alice, $bob), 'the payroll password is hunter2');
        $mine = $chat->directBetween($mallory, $alice);

        Livewire::actingAs($mallory)->test(Chat::class, ['conversation' => $mine])
            ->set('replyTo', $secret->id)
            ->assertDontSee('hunter2');
    }

    public function test_chat_history_size_and_send_rate_are_limited(): void
    {
        $admin = User::factory()->admin()->create();
        $channel = app(ChatService::class)->createChannel($admin, 'Team', null, []);

        $component = Livewire::actingAs($admin)->test(Chat::class, ['conversation' => $channel])
            ->set('limit', 1_000_000)
            ->assertSet('limit', Chat::MAX_HISTORY);

        foreach (range(1, 30) as $i) {
            $component->set('body', "msg {$i}")->call('send');
        }
        $component->set('body', 'one too many')->call('send')->assertHasErrors('body');

        $this->assertSame(30, $channel->messages()->count());
    }

    public function test_login_is_throttled_per_account_even_from_many_ips(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 20) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"]);
            Livewire::test(Login::class)->set('email', $user->email)->set('password', 'wrong')->call('login');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9']);
        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'password')->call('login')
            ->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_forgot_password_requests_are_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            Livewire::test(ForgotPassword::class)->set('email', "person{$i}@example.com")->call('sendLink')->assertHasNoErrors();
        }

        Livewire::test(ForgotPassword::class)->set('email', 'another@example.com')->call('sendLink')->assertHasErrors('email');
    }

    public function test_changing_password_signs_out_other_devices_but_not_this_one(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        Livewire::actingAs($user)->test(Settings::class)
            ->set('current_password', 'password')
            ->set('password', 'a-new-password-123')
            ->set('password_confirmation', 'a-new-password-123')
            ->call('changePassword')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_old_sessions_stop_working_after_an_admin_resets_the_password(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->get('/dashboard')->assertOk();

        $member->forceFill(['password' => 'reset-by-admin-123'])->save();

        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_current_password_guessing_is_rate_limited(): void
    {
        $component = Livewire::actingAs(User::factory()->create())->test(Settings::class);

        foreach (range(1, 5) as $i) {
            $component->set('current_password', "guess{$i}")->set('password', 'new-password-123')
                ->set('password_confirmation', 'new-password-123')->call('changePassword');
        }

        $component->set('current_password', 'password')->call('changePassword')
            ->assertHasErrors('current_password')
            ->assertSee('Too many attempts');
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_admins_can_create_projects_in_any_department(): void
    {
        $admin = User::factory()->admin()->create(['department_id' => Department::factory()->create()->id]);
        $other = Department::factory()->create();

        Livewire::actingAs($admin)->test(Projects::class)
            ->call('create')
            ->set('form.name', 'Cross-team site')
            ->set('form.department_id', $other->id)
            ->call('saveProject')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('projects', ['name' => 'Cross-team site', 'department_id' => $other->id]);
    }

    public function test_meetings_cannot_be_linked_to_unknown_projects(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())->test(Meetings::class)
            ->call('create')
            ->set('form.title', 'Sync')
            ->set('form.project_id', 999999)
            ->set('form.participant_ids', [(string) User::factory()->create()->id])
            ->call('save')
            ->assertHasErrors('form.project_id');
    }

    public function test_admin_password_reset_ends_that_persons_sessions(): void
    {
        $super = User::factory()->superAdmin()->create();
        $member = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'member-laptop', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);

        Livewire::actingAs($super)->test(People::class)
            ->call('edit', $member->id)
            ->set('password', 'fresh-password-99')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'member-laptop']);
    }

    public function test_mobile_number_must_look_like_a_phone_number(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Settings::class)
            ->set('phone', '<script>alert(1)</script>')
            ->call('saveProfile')
            ->assertHasErrors('phone');
    }

    public function test_private_files_are_not_served_from_the_storage_url(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->get('/storage/employee-documents/1/secret.pdf')->assertNotFound();
    }
}
