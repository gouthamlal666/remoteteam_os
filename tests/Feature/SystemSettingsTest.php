<?php

namespace Tests\Feature;

use App\Livewire\Settings as MySettings;
use App\Livewire\SystemSettings;
use App\Models\Conversation;
use App\Models\DailyReport;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Notifications\Alert;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\WhatsApp\WhatsAppSender;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->superAdmin()->create(['phone' => '+91 98765 43210']);
    }

    public function test_only_super_admins_can_open_system_settings(): void
    {
        $this->actingAs($this->super)->get('/system-settings')->assertOk()->assertSee('Company name');
        $this->actingAs(User::factory()->admin()->create())->get('/system-settings')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/system-settings')->assertForbidden();
    }

    public function test_company_name_and_logo_show_across_the_app(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->super)->test(SystemSettings::class)
            ->set('company_name', 'Acme Remote')
            ->call('saveGeneral')
            ->assertHasNoErrors()
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->assertHasNoErrors();

        $this->assertSame('Acme Remote', Settings::companyName());
        auth()->logout();
        $this->get('/login')->assertSee('Acme Remote');
        $this->get(route('branding.logo'))->assertOk();
    }

    public function test_secrets_are_encrypted_and_never_sent_back(): void
    {
        Livewire::actingAs($this->super)->test(SystemSettings::class)
            ->set('tab', 'notifications')
            ->set('whatsapp_enabled', true)
            ->set('whatsapp_driver', 'meta')
            ->set('meta_phone_number_id', '1234567890')
            ->set('meta_token', 'EAAG-super-secret')
            ->call('saveNotifications')
            ->assertHasNoErrors()
            ->assertSet('meta_token', '')
            ->assertDontSee('EAAG-super-secret');

        $raw = Setting::find('whatsapp.meta_token')->value;
        $this->assertStringNotContainsString('EAAG-super-secret', $raw);
        $this->assertSame('EAAG-super-secret', Settings::get('whatsapp.meta_token'));

        // Saving again with a blank token keeps the stored one.
        Livewire::actingAs($this->super)->test(SystemSettings::class)->set('tab', 'notifications')->call('saveNotifications')->assertHasNoErrors();
        $this->assertSame('EAAG-super-secret', Settings::get('whatsapp.meta_token'));
    }

    public function test_whatsapp_only_goes_to_opted_in_people_for_selected_alerts(): void
    {
        Settings::set(['notifications.whatsapp_enabled' => true, 'whatsapp.events' => ['task.assigned']]);
        $optedIn = User::factory()->create(['phone' => '9876543210', 'whatsapp_notifications' => true]);
        $optedOut = User::factory()->create(['phone' => '9876543211', 'whatsapp_notifications' => false]);
        $noPhone = User::factory()->create(['phone' => null, 'whatsapp_notifications' => true]);

        $assigned = new Alert('task.assigned', 'New task', 'Do it', '/x', mail: true);
        $this->assertContains(WhatsAppChannel::class, $assigned->via($optedIn));
        $this->assertNotContains(WhatsAppChannel::class, $assigned->via($optedOut));
        $this->assertNotContains(WhatsAppChannel::class, $assigned->via($noPhone));
        $this->assertNotContains(WhatsAppChannel::class, (new Alert('chat.direct', 'a', 'b', '/'))->via($optedIn));

        // Test mode: sent to the log and recorded, with the country code added.
        $optedIn->notify($assigned);
        $this->assertDatabaseHas('whatsapp_messages', ['to' => '+919876543210', 'status' => 'logged', 'type' => 'task.assigned']);
    }

    public function test_meta_and_twilio_requests_and_failures(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]]),
            'api.twilio.com/*' => Http::response(['message' => 'Invalid From number'], 400),
        ]);

        Settings::set(['whatsapp.driver' => 'meta', 'whatsapp.meta_phone_number_id' => '555', 'whatsapp.meta_token' => 'tok']);
        $ok = app(WhatsAppSender::class)->send('+44 7700 900123', 'Hello', 'World', 'https://x.test');
        $this->assertSame('sent', $ok->status);
        $this->assertSame('wamid.1', $ok->provider_id);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/555/messages') && $r['to'] === '447700900123' && $r['template']['name'] === 'remoteteam_alert' && $r->hasHeader('Authorization', 'Bearer tok'));

        Settings::set(['whatsapp.driver' => 'twilio', 'whatsapp.twilio_sid' => 'AC1', 'whatsapp.twilio_token' => 't', 'whatsapp.twilio_from' => '+14155238886']);
        $failed = app(WhatsAppSender::class)->send('+447700900123', 'Hello', 'World', 'https://x.test');
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('Invalid From number', $failed->error);
    }

    public function test_phone_numbers_are_normalised(): void
    {
        $this->assertSame('+919876543210', WhatsAppSender::normalise('098765 43210'));
        $this->assertSame('+447700900123', WhatsAppSender::normalise('+44 (7700) 900-123'));
        $this->assertSame('+447700900123', WhatsAppSender::normalise('00447700900123'));
        $this->assertNull(WhatsAppSender::normalise('call me'));
    }

    public function test_send_test_button_records_a_delivery(): void
    {
        Livewire::actingAs($this->super)->test(SystemSettings::class)
            ->set('tab', 'notifications')
            ->call('sendTest')
            ->assertDispatched('notify');

        $this->assertSame(1, WhatsappMessage::where('type', 'test')->count());
    }

    public function test_people_opt_in_to_whatsapp_only_with_a_valid_mobile(): void
    {
        Settings::set(['notifications.whatsapp_enabled' => true]);
        $user = User::factory()->create(['phone' => null]);

        Livewire::actingAs($user)->test(MySettings::class)
            ->set('whatsapp_notifications', true)
            ->call('saveProfile')
            ->assertHasErrors('phone')
            ->set('phone', '+91 90000 11111')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->whatsapp_notifications);
    }

    public function test_security_settings_apply(): void
    {
        Livewire::actingAs($this->super)->test(SystemSettings::class)
            ->set('tab', 'security')
            ->set('min_password_length', 12)
            ->set('member_direct_messages', false)
            ->call('saveSecurity');

        [$a, $b] = User::factory()->count(2)->create();
        $admin = User::factory()->admin()->create();
        $this->assertFalse($a->can('messageUser', [Conversation::class, $b]));
        $this->assertTrue($a->can('messageUser', [Conversation::class, $admin]));

        Livewire::actingAs($a)->test(MySettings::class)
            ->set('current_password', 'password')
            ->set('password', 'short-pass1')
            ->set('password_confirmation', 'short-pass1')
            ->call('changePassword')
            ->assertHasErrors('password');
    }

    public function test_daily_report_reminder_goes_once_to_people_without_a_report(): void
    {
        Notification::fake();
        $this->travelTo(now()->next('Monday')->setTime(18, 30));
        Settings::set(['work.report_reminder_enabled' => true, 'work.report_reminder_time' => '18:00']);

        $done = User::factory()->create();
        DailyReport::create(['user_id' => $done->id, 'report_date' => today(), 'completed' => 'x', 'hours_worked' => 8]);
        $pending = User::factory()->create();

        $this->artisan('reminders:send');
        $this->artisan('reminders:send');

        Notification::assertSentToTimes($pending, Alert::class, 1);
        Notification::assertNotSentTo($done, Alert::class);
        Notification::assertNotSentTo($this->super, Alert::class);
    }

    public function test_no_report_reminder_on_non_working_days(): void
    {
        Notification::fake();
        $this->travelTo(now()->next('Sunday')->setTime(19, 0));
        Settings::set(['work.report_reminder_enabled' => true, 'work.report_reminder_time' => '18:00']);
        User::factory()->create();

        $this->artisan('reminders:send');

        Notification::assertNothingSent();
    }
}
