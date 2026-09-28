<?php

namespace Tests\Feature;

use App\Livewire\Meetings\Index;
use App\Livewire\Meetings\Show;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_schedules_meeting_and_invitee_responds(): void
    {
        $admin = User::factory()->admin()->create();
        $dev = User::factory()->create();

        Livewire::actingAs($admin)->test(Index::class)
            ->call('create')
            ->set('form.title', 'Weekly Development Meeting')
            ->set('form.date', today()->addDay()->toDateString())
            ->set('form.time', '10:00')
            ->set('form.duration_minutes', 30)
            ->set('form.meeting_link', 'https://meet.google.com/abc')
            ->set('form.participant_ids', [(string) $dev->id])
            ->call('save')
            ->assertHasNoErrors();

        $meeting = Meeting::firstOrFail();
        $this->assertEqualsCanonicalizing([$admin->id, $dev->id], $meeting->participants->pluck('id')->all());

        Livewire::actingAs($dev)->test(Show::class, ['meeting' => $meeting])->call('respond', 'accepted');
        $this->assertSame('accepted', $meeting->participants()->find($dev->id)->pivot->response);

        // Invitees cannot edit or cancel.
        Livewire::actingAs($dev)->test(Show::class, ['meeting' => $meeting])->call('cancel')->assertForbidden();
    }

    public function test_meeting_link_must_be_http(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())->test(Index::class)
            ->call('create')
            ->set('form.title', 'X')
            ->set('form.meeting_link', 'javascript:alert(1)')
            ->set('form.participant_ids', [(string) User::factory()->create()->id])
            ->call('save')
            ->assertHasErrors('form.meeting_link');
    }

    public function test_members_cannot_schedule_or_see_meetings_they_are_not_in(): void
    {
        $member = User::factory()->create();
        $meeting = Meeting::create(['organizer_id' => User::factory()->admin()->create()->id, 'title' => 'Private', 'starts_at' => now()->addDay()]);

        Livewire::actingAs($member)->test(Index::class)->call('create')->assertForbidden();
        $this->actingAs($member)->get(route('meetings.show', $meeting))->assertForbidden();
    }

    public function test_completing_records_attendance_and_feeds_performance(): void
    {
        $admin = User::factory()->admin()->create();
        [$present, $absent] = User::factory()->count(2)->create();
        $meeting = Meeting::create(['organizer_id' => $admin->id, 'title' => 'Standup', 'starts_at' => now()->subHour()]);
        $meeting->participants()->attach([$admin->id, $present->id, $absent->id]);

        Livewire::actingAs($admin)->test(Show::class, ['meeting' => $meeting])
            ->set('attendance', [$admin->id => true, $present->id => true, $absent->id => false])
            ->set('notes', 'Decided to ship Friday')
            ->call('complete');

        $this->assertSame(Meeting::COMPLETED, $meeting->fresh()->status);
        $this->assertSame(100, $present->meetingAttendanceRate());
        $this->assertSame(0, $absent->meetingAttendanceRate());
    }
}
