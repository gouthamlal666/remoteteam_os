<?php

namespace Tests\Feature;

use App\Livewire\Chat\Index;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_chat_is_created_once_per_pair(): void
    {
        [$a, $b] = User::factory()->count(2)->create();
        $chat = app(ChatService::class);

        $first = $chat->directBetween($a, $b);
        $second = $chat->directBetween($b, $a);

        $this->assertTrue($first->is($second));
        $this->assertSame(2, $first->members()->count());
    }

    public function test_member_can_message_admin_and_admin_sees_unread(): void
    {
        $member = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($member)->test(Index::class)
            ->call('startDirect', $admin->id)
            ->set('body', 'Hi @'.$admin->handle().' can we talk?')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSee('can we talk?');

        $this->assertSame(1, $admin->unreadMessagesCount());
        $this->assertSame(0, $member->unreadMessagesCount());

        // Opening the conversation marks it read.
        Livewire::actingAs($admin)->test(Index::class, ['conversation' => Conversation::first()]);
        $this->assertSame(0, $admin->fresh()->unreadMessagesCount());
    }

    public function test_outsiders_cannot_read_a_conversation(): void
    {
        [$a, $b] = User::factory()->count(2)->create();
        $conversation = app(ChatService::class)->directBetween($a, $b);
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->get(route('chat', $conversation))->assertForbidden();
        $this->actingAs($a)->get(route('chat', $conversation))->assertOk();
    }

    public function test_attachments_react_pin_reply_and_search(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $channel = app(ChatService::class)->createChannel($admin, 'Dev Team', null, [$member->id]);

        $lw = Livewire::actingAs($member)->test(Index::class, ['conversation' => $channel])
            ->set('files', [UploadedFile::fake()->image('screenshot.png')])
            ->call('send')
            ->assertHasNoErrors();

        $message = $channel->messages()->first();
        $this->assertCount(1, $message->attachments);

        $lw->call('react', $message->id, '👍')
            ->call('togglePin', $message->id)
            ->call('startReply', $message->id)
            ->set('body', 'Looks good')
            ->call('send');

        $this->assertSame(1, $message->reactions()->count());
        $this->assertNotNull($message->fresh()->pinned_at);
        $this->assertSame($message->id, $channel->messages()->latest('id')->first()->reply_to_id);

        $lw->set('search', 'Looks')->assertSee('Looks good');

        $this->actingAs($admin)->get(route('chat.attachments.show', $message->attachments->first()))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('chat.attachments.show', $message->attachments->first()))->assertForbidden();
    }

    public function test_members_cannot_create_channels_or_delete_others_messages(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $channel = app(ChatService::class)->createChannel($admin, 'Team', null, [$member->id]);
        $message = app(ChatService::class)->send($admin, $channel, 'Hello');

        Livewire::actingAs($member)->test(Index::class, ['conversation' => $channel])
            ->set('channelName', 'Secret')
            ->set('channelMembers', [$admin->id])
            ->call('createChannel')
            ->assertForbidden();

        Livewire::actingAs($member)->test(Index::class, ['conversation' => $channel])
            ->call('deleteMessage', $message->id)
            ->assertForbidden();
    }

    public function test_mentions_are_highlighted_and_html_is_escaped(): void
    {
        $user = User::factory()->create();
        $channel = app(ChatService::class)->createChannel(User::factory()->admin()->create(), 'X', null, [$user->id]);
        $message = app(ChatService::class)->send($user, $channel, '<script>alert(1)</script> hi @rahul.menon');

        $html = (string) $message->renderedBody();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('>@rahul.menon</span>', $html);
    }
}
