<?php

namespace Tests\Feature;

use App\Livewire\Projects\Index as Projects;
use App\Livewire\Tasks\Index as Tasks;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_a_client_shows_only_their_projects_and_tasks(): void
    {
        $super = User::factory()->superAdmin()->create();
        $shopNest = Client::factory()->create(['name' => 'ShopNest']);
        $acme = Client::factory()->create(['name' => 'Acme Corp']);
        $shop = Project::factory()->create(['name' => 'Shop Site', 'client_id' => $shopNest->id]);
        Project::factory()->create(['name' => 'Shop App', 'client_id' => $shopNest->id]);
        $acmePortal = Project::factory()->create(['name' => 'Acme Portal', 'client_id' => $acme->id]);
        Project::factory()->create(['name' => 'Intranet', 'client_id' => null]);
        Task::factory()->create(['project_id' => $shop->id, 'title' => 'Checkout flow']);
        Task::factory()->create(['project_id' => $acmePortal->id, 'title' => 'Acme login']);

        Livewire::actingAs($super)->test(Projects::class)
            ->assertSee('Acme Portal')
            ->set('client', (string) $shopNest->id)
            ->assertSee('Shop Site')->assertSee('Shop App')
            ->assertDontSee('Acme Portal')->assertDontSee('Intranet')
            ->assertSee('Checkout flow')->assertDontSee('Acme login')
            ->call('clearClient')
            ->assertSee('Acme Portal');

        Livewire::actingAs($super)->test(Projects::class)->set('client', 'internal')
            ->assertSee('Intranet')->assertDontSee('Shop Site');

        Livewire::actingAs($super)->test(Tasks::class, ['client' => (string) $shopNest->id])
            ->assertSee('Checkout flow')->assertDontSee('Acme login');
    }

    public function test_client_view_respects_what_the_viewer_can_see(): void
    {
        $member = User::factory()->create();
        $shopNest = Client::factory()->create(['name' => 'ShopNest']);
        $mine = Project::factory()->create(['name' => 'My Shop Work', 'client_id' => $shopNest->id]);
        $mine->members()->attach($member);
        Project::factory()->create(['name' => 'Hidden Shop Work', 'client_id' => $shopNest->id]);

        Livewire::actingAs($member)->test(Projects::class)->set('client', (string) $shopNest->id)
            ->assertSee('My Shop Work')->assertDontSee('Hidden Shop Work');
    }
}
