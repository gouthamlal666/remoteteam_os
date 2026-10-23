<?php

namespace App\Livewire\Clients;

use App\Enums\TaskStatus;
use App\Livewire\Forms\ClientForm;
use App\Models\Client;
use App\Services\ClientService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Clients')]
class Index extends Component
{
    public ClientForm $form;

    public bool $showForm = false;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Client::class);
    }

    public function create(): void
    {
        $this->authorize('create', Client::class);
        $this->form->reset();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function saveClient(ClientService $clients)
    {
        $this->authorize('create', Client::class);
        $client = $clients->save(auth()->user(), null, $this->form->payload());

        return $this->redirectRoute('clients.show', $client, navigate: true);
    }

    public function render()
    {
        $clients = Client::query()
            ->with('primaryContact')
            ->withCount([
                'projects',
                'tasks as open_tasks_count' => fn ($q) => $q->where('tasks.status', '!=', TaskStatus::Completed->value),
            ])
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('legal_name', 'like', "%{$this->search}%")
                ->orWhere('industry', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderByRaw("case status when 'active' then 0 when 'prospect' then 1 when 'paused' then 2 else 3 end")
            ->orderBy('name')
            ->get();

        return view('livewire.clients.index', ['clients' => $clients]);
    }
}
