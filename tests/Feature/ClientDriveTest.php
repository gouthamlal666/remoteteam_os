<?php

namespace Tests\Feature;

use App\Livewire\Clients\Drive;
use App\Models\Client;
use App\Models\ClientFile;
use App\Models\ClientFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ClientDriveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
        $this->client = Client::factory()->create();
    }

    public function test_folders_nest_and_uploads_land_in_the_open_folder(): void
    {
        $drive = Livewire::actingAs($this->admin)->test(Drive::class, ['client' => $this->client])
            ->call('createSuggestedFolders');

        $this->assertSame(count(ClientFolder::SUGGESTED), $this->client->folders()->count());
        $images = $this->client->folders()->where('name', 'Images')->first();

        $drive->call('openFolder', $images->id)
            ->call('editFolder')->set('folderName', 'Instagram')->call('saveFolder')
            ->set('uploads', [UploadedFile::fake()->image('banner.jpg')])
            ->assertHasNoErrors()
            ->assertSee('banner');

        $this->assertSame($images->id, $this->client->folders()->where('name', 'Instagram')->value('parent_id'));
        $this->assertSame($images->id, $this->client->files()->where('title', 'banner')->value('folder_id'));
    }

    public function test_deleting_a_folder_keeps_its_contents(): void
    {
        $folder = ClientFolder::factory()->create(['client_id' => $this->client->id, 'name' => 'Old']);
        $child = ClientFolder::factory()->create(['client_id' => $this->client->id, 'parent_id' => $folder->id, 'name' => 'Inner']);
        $file = ClientFile::factory()->create(['client_id' => $this->client->id, 'folder_id' => $folder->id]);

        Livewire::actingAs($this->admin)->test(Drive::class, ['client' => $this->client])->call('deleteFolder', $folder->id);

        $this->assertModelMissing($folder);
        $this->assertNull($file->fresh()->folder_id);
        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_items_can_be_renamed_and_moved_but_only_within_this_client(): void
    {
        $folder = ClientFolder::factory()->create(['client_id' => $this->client->id, 'name' => 'Docs']);
        $foreign = ClientFolder::factory()->create();
        $file = ClientFile::factory()->create(['client_id' => $this->client->id]);

        Livewire::actingAs($this->admin)->test(Drive::class, ['client' => $this->client])
            ->call('editItem', $file->id)
            ->set('itemTitle', 'Signed agreement')
            ->set('moveTo', (string) $foreign->id)
            ->call('saveItem')
            ->assertHasErrors('moveTo')
            ->set('moveTo', (string) $folder->id)
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertSame(['Signed agreement', $folder->id], [$file->fresh()->title, $file->fresh()->folder_id]);
    }

    public function test_video_and_drive_links_get_embeddable_previews(): void
    {
        $cases = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'https://drive.google.com/file/d/1AbC-dEf_ghI/view?usp=sharing' => 'https://drive.google.com/file/d/1AbC-dEf_ghI/preview',
            'https://docs.google.com/document/d/1XyZ/edit' => 'https://docs.google.com/document/d/1XyZ/preview',
            'https://vimeo.com/76979871' => 'https://player.vimeo.com/video/76979871',
            'https://www.loom.com/share/abc123' => 'https://www.loom.com/embed/abc123',
            'https://example.com/page' => null,
        ];

        foreach ($cases as $url => $embed) {
            $item = new ClientFile(['kind' => 'link', 'url' => $url]);
            $this->assertSame($embed, $item->embedUrl(), $url);
        }

        Livewire::actingAs($this->admin)->test(Drive::class, ['client' => $this->client])
            ->set('showLink', true)
            ->set('linkUrl', 'https://youtu.be/dQw4w9WgXcQ')
            ->call('addLink')
            ->assertHasNoErrors()
            ->set('linkUrl', 'javascript:alert(1)')
            ->call('addLink')
            ->assertHasErrors('linkUrl');

        $link = $this->client->files()->first();
        $this->assertSame('YouTube video', $link->title);
        $this->assertSame('video', $link->category());
    }

    public function test_inline_previews_are_limited_to_safe_types(): void
    {
        $image = UploadedFile::fake()->image('photo.png')->store('clients/x', 'local');
        $svg = UploadedFile::fake()->createWithContent('icon.svg', '<svg onload="alert(1)"/>')->store('clients/x', 'local');

        $png = ClientFile::factory()->create(['client_id' => $this->client->id, 'path' => $image, 'mime_type' => 'image/png', 'original_name' => 'photo.png']);
        $bad = ClientFile::factory()->create(['client_id' => $this->client->id, 'path' => $svg, 'mime_type' => 'image/svg+xml', 'original_name' => 'icon.svg']);

        $this->actingAs($this->admin)->get(route('client-files.view', $png))
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->admin)->get(route('client-files.view', $bad))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('client-files.view', $png))->assertForbidden();
    }
}
