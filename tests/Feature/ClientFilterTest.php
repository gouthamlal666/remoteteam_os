<?php

namespace Tests\Feature;

use App\Livewire\Projects\Index as Projects;
use App\Livewire\Tasks\Index as Tasks;
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
        $shop = Project::factory()->create(['name' => 'Shop Site', 'client' => 'ShopNest']);
        $shopApp = Project::factory()->create(['name' => 'Shop App', 'client' => 'ShopNest']);
        $acme = Project::factory()->create(['name' => 'Acme Portal', 'client' => 'Acme Corp']);
        $internal = Project::factory()->create(['name' => 'Intranet', 'client' => null]);
        Task::factory()->create(['project_id' => $shop->id, 'title' => 'Checkout flow']);
        Task::factory()->create(['project_id' => $acme->id, 'title' => 'Acme login']);

        Livewire::actingAs($super)->test(Projects::class)
            ->assertSee('Acme Portal')
            ->set('client', 'ShopNest')
            ->assertSee('Shop Site')->assertSee('Shop App')
            ->assertDontSee('Acme Portal')->assertDontSee('Intranet')
            ->assertSee('Checkout flow')->assertDontSee('Acme login')
            ->call('clearClient')
            ->assertSee('Acme Portal');

        // "Internal" also covers projects without a client.
        Livewire::actingAs($super)->test(Projects::class)->set('client', 'Internal')
            ->assertSee('Intranet')->assertDontSee('Shop Site');

        Livewire::actingAs($super)->test(Tasks::class, ['client' => 'ShopNest'])
            ->assertSee('Checkout flow')->assertDontSee('Acme login');
    }

    public function test_client_view_respects_what_the_viewer_can_see(): void
    {
        $member = User::factory()->create();
        $mine = Project::factory()->create(['name' => 'My Shop Work', 'client' => 'ShopNest']);
        $mine->members()->attach($member);
        Project::factory()->create(['name' => 'Hidden Shop Work', 'client' => 'ShopNest']);

        Livewire::actingAs($member)->test(Projects::class)->set('client', 'ShopNest')
            ->assertSee('My Shop Work')->assertDontSee('Hidden Shop Work');
    }
}
