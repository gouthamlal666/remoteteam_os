<?php

namespace Tests\Feature;

use App\Livewire\Clients\Drive;
use App\Livewire\Clients\Index as Clients;
use App\Livewire\Clients\Show as ClientPage;
use App\Livewire\Projects\Index as Projects;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Project;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ClientsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
        $this->client = Client::factory()->create(['name' => 'ShopNest']);
    }

    public function test_only_managers_can_open_clients(): void
    {
        $this->actingAs($this->admin)->get(route('clients.index'))->assertOk()->assertSee('ShopNest');
        $this->actingAs($this->admin)->get(route('clients.show', $this->client))->assertOk();

        $member = User::factory()->create();
        $this->actingAs($member)->get(route('clients.index'))->assertForbidden();
        $this->actingAs($member)->get(route('clients.show', $this->client))->assertForbidden();
    }

    public function test_admin_creates_a_client_with_details(): void
    {
        Livewire::actingAs($this->admin)->test(Clients::class)
            ->call('create')
            ->set('form.name', 'Maths Eleven')
            ->set('form.website', 'https://mathseleven.co.uk')
            ->set('form.email', 'hello@mathseleven.co.uk')
            ->set('form.phone', '+44 7700 900123')
            ->call('saveClient')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('clients', ['name' => 'Maths Eleven', 'website' => 'https://mathseleven.co.uk', 'created_by' => $this->admin->id]);
    }

    public function test_client_names_are_unique_and_websites_must_be_http(): void
    {
        Livewire::actingAs($this->admin)->test(Clients::class)
            ->call('create')
            ->set('form.name', 'ShopNest')
            ->set('form.website', 'javascript:alert(1)')
            ->call('saveClient')
            ->assertHasErrors(['form.name', 'form.website']);
    }

    public function test_projects_can_pick_or_quick_create_a_client(): void
    {
        Livewire::actingAs($this->admin)->test(Projects::class)
            ->call('create')
            ->set('form.name', 'Shop redesign')
            ->set('form.client_id', (string) $this->client->id)
            ->call('saveProject')
            ->assertHasNoErrors();
        $this->assertSame($this->client->id, Project::where('name', 'Shop redesign')->value('client_id'));

        Livewire::actingAs($this->admin)->test(Projects::class)
            ->call('create')
            ->set('form.name', 'SEO retainer')
            ->set('form.new_client', 'Brand New Ltd')
            ->call('saveProject')
            ->assertHasNoErrors();
        $this->assertSame(Client::where('name', 'Brand New Ltd')->value('id'), Project::where('name', 'SEO retainer')->value('client_id'));
    }

    public function test_contacts_and_links(): void
    {
        Livewire::actingAs($this->admin)->test(ClientPage::class, ['client' => $this->client])
            ->call('editContact')
            ->set('contactName', 'Priya Rao')
            ->set('contactEmail', 'priya@shopnest.com')
            ->call('saveContact')
            ->assertHasNoErrors()
            ->call('editLink')
            ->set('linkType', 'admin')
            ->set('linkUrl', 'https://shopnest.com/wp-admin')
            ->call('saveLink')
            ->assertHasNoErrors()
            ->call('editLink')
            ->set('linkUrl', 'javascript:alert(1)')
            ->call('saveLink')
            ->assertHasErrors('linkUrl');

        $this->assertTrue($this->client->contacts()->first()->is_primary, 'The first contact becomes the main contact.');
        $this->assertSame('Admin panel / CMS', $this->client->links()->first()->label);
    }

    public function test_passwords_are_encrypted_and_hidden(): void
    {
        $credential = ClientCredential::factory()->create(['client_id' => $this->client->id, 'password' => 'Sup3r-Secret!']);

        $raw = DB::table('client_credentials')->where('id', $credential->id)->value('password');
        $this->assertStringNotContainsString('Sup3r-Secret!', $raw);
        $this->assertArrayNotHasKey('password', $credential->toArray());

        // The page never contains the secret until it is explicitly revealed.
        $this->actingAs($this->admin)->get(route('clients.show', [$this->client, 'tab' => 'vault']))
            ->assertOk()->assertDontSee('Sup3r-Secret!');
    }

    public function test_reveal_requires_unlocking_with_your_own_password(): void
    {
        $credential = ClientCredential::factory()->create(['client_id' => $this->client->id, 'password' => 'Sup3r-Secret!']);
        $page = Livewire::actingAs($this->admin)->test(ClientPage::class, ['client' => $this->client]);

        $this->assertNull($page->instance()->reveal($credential->id, app(ClientService::class)));

        $page->call('unlockVault', 'wrong-password')->assertHasErrors('vault');
        $page->call('unlockVault', 'password')->assertHasNoErrors();

        $secret = $page->instance()->reveal($credential->id, app(ClientService::class));
        $this->assertSame('Sup3r-Secret!', $secret['password']);

        // Reveals are audited without the secret itself.
        $this->assertDatabaseHas('activities', ['action' => 'client.credential_viewed', 'user_id' => $this->admin->id]);
        $this->assertDatabaseMissing('activities', ['description' => 'Sup3r-Secret!']);
    }

    public function test_vault_unlock_is_rate_limited(): void
    {
        $page = Livewire::actingAs($this->admin)->test(ClientPage::class, ['client' => $this->client]);

        foreach (range(1, 5) as $i) {
            $page->call('unlockVault', "guess-{$i}");
        }

        $page->call('unlockVault', 'password')->assertHasErrors('vault');
        $this->assertFalse($page->instance()->vaultUnlocked());
    }

    public function test_saving_a_login_needs_an_unlocked_vault_and_keeps_password_when_blank(): void
    {
        Livewire::actingAs($this->admin)->test(ClientPage::class, ['client' => $this->client])
            ->call('editCredential')
            ->set('credLabel', 'Hostinger')
            ->set('credPassword', 'hosting-pass-1')
            ->call('saveCredential')
            ->assertForbidden();

        $page = Livewire::actingAs($this->admin)->test(ClientPage::class, ['client' => $this->client])
            ->call('unlockVault', 'password')
            ->call('editCredential')
            ->set('credLabel', 'Hostinger')
            ->set('credUsername', 'shopnest')
            ->set('credPassword', 'hosting-pass-1')
            ->call('saveCredential')
            ->assertHasNoErrors();

        $credential = $this->client->credentials()->first();
        $page->call('editCredential', $credential->id)->set('credLabel', 'Hostinger (main)')->call('saveCredential');

        $this->assertSame('hosting-pass-1', $credential->fresh()->password);
        $this->assertSame('Hostinger (main)', $credential->fresh()->label);
    }

    public function test_drive_uploads_download_and_access(): void
    {
        Livewire::actingAs($this->admin)->test(Drive::class, ['client' => $this->client])
            ->set('uploads', [UploadedFile::fake()->create('agreement.pdf', 200, 'application/pdf'), UploadedFile::fake()->image('logo.png')])
            ->assertHasNoErrors()
            ->set('uploads', [UploadedFile::fake()->create('evil.php', 1)])
            ->assertHasErrors('uploads.0');

        $this->assertSame(2, $this->client->files()->count());
        $file = $this->client->files()->where('title', 'agreement')->first();
        Storage::disk('local')->assertExists($file->path);

        $this->actingAs($this->admin)->get(route('client-files.show', $file))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('client-files.show', $file))->assertForbidden();
    }

    public function test_logo_is_shown_to_everyone_signed_in(): void
    {
        Livewire::actingAs($this->admin)->test(ClientPage::class, ['client' => $this->client])
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->assertHasNoErrors();

        $this->actingAs(User::factory()->create())->get($this->client->fresh()->logoUrl())->assertOk();
    }

    public function test_deleting_a_client_keeps_projects_as_internal(): void
    {
        $project = Project::factory()->create(['client_id' => $this->client->id]);

        Livewire::actingAs($this->admin)->test(ClientPage::class, ['client' => $this->client])
            ->call('delete')
            ->assertRedirect(route('clients.index'));

        $this->assertModelMissing($this->client);
        $this->assertNull($project->fresh()->client_id);
    }

    public function test_members_cannot_change_clients(): void
    {
        Livewire::actingAs(User::factory()->create())->test(ClientPage::class, ['client' => $this->client])->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(Drive::class, ['client' => $this->client])->assertForbidden();
    }

    public function test_extra_details_and_login_categories(): void
    {
        Livewire::actingAs($this->admin)->test(ClientPage::class, ['client' => $this->client])
            ->call('editDetail')
            ->set('detailLabel', 'Hosting provider')
            ->set('detailValue', 'Hostinger')
            ->call('saveDetail')
            ->assertHasNoErrors()
            ->assertSee('Hostinger')
            ->call('unlockVault', 'password')
            ->call('editCredential')
            ->set('credCategory', 'hosting')
            ->set('credLabel', 'hPanel')
            ->set('credPassword', 'x-secret-1')
            ->call('saveCredential')
            ->assertHasNoErrors();

        $this->assertSame('Hostinger', $this->client->details()->first()->value);
        $this->assertSame('hosting', $this->client->credentials()->first()->category);
    }
}
